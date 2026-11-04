#!/usr/bin/env node
// Maintainer tool (Node 18, no network): vendors the upstream tarteaucitron services file and
// generates Resources/catalogue/{catalogue.json,fragments/*.js,REPORT.md}. Never runs at runtime.
//
//   node tools/catalogue/build.mjs --source <tarteaucitron.services.js> [--section3 <json>] [--version 1.37.0] [--expected 249]
// Expected count: 249 root assignments in v1.37.0 (a lowercase-only grep finds 248 and misses `intercomChat`).
// --section3 (default: tools/catalogue/tarteaucitron-section3-services.json) is a JSON listing of the services (key, user_params, needs_html_markup, snippet), used to
// cross-check parameters and to fill the HTML markup of services that need it. See docs/maintaining-the-catalogue.md.
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { splitServices } from './split.mjs';
import { detectExclusion, readFallbackClasses, readMetadata, readParams } from './sandbox.mjs';
import { GOOGLE_TAG_KEYS, OTHER_PURPOSE, TYPE_TO_PURPOSE } from './purposes.mjs';
import { renderReport } from './report.mjs';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

function args() {
  const out = { version: '1.37.0', expected: 249, source: null, section3: join(ROOT, 'tools/catalogue/tarteaucitron-section3-services.json') };
  const argv = process.argv.slice(2);
  for (let i = 0; i < argv.length; i += 2) {
    const name = argv[i].replace(/^--/, '');
    if (!(name in out) || argv[i + 1] === undefined) throw new Error(`Unknown or incomplete option: ${argv[i]}`);
    out[name] = name === 'expected' ? Number(argv[i + 1]) : argv[i + 1];
  }
  if (!out.source) fail('--source <path to the upstream tarteaucitron.services.js> is required.');
  return out;
}

function fail(message) {
  console.error(`catalogue:build FAILED: ${message}`);
  process.exit(1);
}

function extractMarkup(snippet) {
  const start = snippet.indexOf('where you need the service');
  if (start === -1) return '';
  const rest = snippet.slice(start + 'where you need the service'.length);
  const end = rest.indexOf('\u{1F5D1}');
  return (end === -1 ? rest : rest.slice(0, end)).trim();
}

const MIT = `MIT License

Copyright (c) 2014 AmauriC

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
`;

const opts = args();
if (!existsSync(opts.source)) fail(`upstream file not found: ${opts.source}`);
const source = readFileSync(opts.source, 'utf8');
const sha256 = createHash('sha256').update(source).digest('hex');

// 1. Vendor copy (header + upstream, unmodified).
const vendorDir = join(ROOT, 'Resources/vendor/tarteaucitron');
mkdirSync(vendorDir, { recursive: true });
writeFileSync(join(vendorDir, 'LICENSE'), MIT);
writeFileSync(join(vendorDir, 'tarteaucitron.services.js'),
  `/*!\n * tarteaucitron.js services catalogue, vendored copy (unmodified below this header).\n * Upstream: https://github.com/AmauriC/tarteaucitron.js, tag v${opts.version}, file tarteaucitron.services.js\n * Copyright (c) 2014 AmauriC. Licensed under the MIT License (see LICENSE in this directory).\n * sha256 of the upstream file: ${sha256}\n */\n${source}`);

// 2. Split.
const fragments = splitServices(source);
if (fragments.length !== opts.expected) {
  fail(`found ${fragments.length} root assignments "tarteaucitron.services.<key> = {", expected ${opts.expected}. `
    + 'Upstream changed: review the diff, then pass --expected <n>.');
}

const section3 = opts.section3 && existsSync(opts.section3) ? new Map(JSON.parse(readFileSync(opts.section3, 'utf8')).map((s) => [s.key, s])) : null;
const verified = JSON.parse(readFileSync(join(ROOT, 'tools/catalogue/verified.json'), 'utf8'));

const notes = { keyMismatch: [], needConsentFalse: [], otherMapped: [], dynamicCookies: [], iifeCookies: [], kindMismatch: [], callbacks: {}, section3Diff: [], noSection3: [], unmappedOtherStale: [] };
const entries = [];

for (const fragment of fragments) {
  const meta = readMetadata(fragment.code, fragment.key);
  if (meta.innerKey !== fragment.key) notes.keyMismatch.push(`${fragment.key} (key field: ${meta.innerKey})`);
  if (!meta.hasJs) fail(`service "${fragment.key}" has no js()`);
  if (meta.needConsent === false) notes.needConsentFalse.push(fragment.key);

  let purpose = null;
  if (meta.type === 'google') {
    purpose = null;
  } else if (meta.type === 'other') {
    purpose = OTHER_PURPOSE[fragment.key];
    if (!purpose) fail(`service "${fragment.key}" has upstream type "other" and no entry in tools/catalogue/purposes.mjs OTHER_PURPOSE.`);
    notes.otherMapped.push(`${fragment.key} -> ${purpose}`);
  } else {
    purpose = TYPE_TO_PURPOSE[meta.type];
    if (!purpose) fail(`service "${fragment.key}" has unknown upstream type "${meta.type}".`);
  }

  const excluded = detectExclusion(fragment.code, meta.type, fragment.key);
  const { params, callbacks, required } = readParams(fragment.code);
  if (callbacks.length) notes.callbacks[fragment.key] = callbacks;
  if (meta.cookiesIife) notes.iifeCookies.push(fragment.key);
  if (meta.cookiesDynamic) notes.dynamicCookies.push(fragment.key);

  const derivedKind = /tarteaucitron\.engage\s*\(/.test(meta.fallbackSource) ? 'media' : 'script';
  const sec = section3?.get(fragment.key);
  if (section3) {
    if (!sec) notes.noSection3.push(fragment.key);
    else {
      const upstream = [...(sec.user_params ?? [])].sort().join(',');
      const found = params.concat(callbacks).sort().join(',');
      if (upstream !== found) notes.section3Diff.push(`${fragment.key}: listing=[${upstream}] code=[${found}]`);
    }
  }

  const ver = verified[fragment.key];
  let entry;
  if (ver) {
    if (excluded) fail(`verified service "${fragment.key}" is excluded (${excluded}).`);
    const expected = params.filter((n) => !(ver.ignoreParams ?? []).includes(n));
    const declared = Object.keys(ver.params);
    if (expected.slice().sort().join(',') !== declared.slice().sort().join(',')) {
      fail(`verified service "${fragment.key}": params in verified.json [${declared}] differ from params read in the fragment [${expected}].`);
    }
    if (ver.kind !== derivedKind) notes.kindMismatch.push(`${fragment.key}: verified=${ver.kind} derived=${derivedKind}`);
    entry = {
      key: fragment.key, name: ver.name ?? meta.name, upstreamType: meta.type, purpose: ver.purpose, verified: true, kind: ver.kind,
      googleTag: ver.googleTag ?? GOOGLE_TAG_KEYS.has(fragment.key), uri: meta.uri,
      params: declared.map((name) => ({ name, required: ver.params[name].required, label: ver.params[name].label, help: ver.params[name].help, pattern: ver.params[name].pattern })),
      cookies: ver.cookies, storage: ver.storage ?? [], domains: ver.domains,
    };
  } else {
    entry = {
      key: fragment.key, name: meta.name ?? fragment.key, upstreamType: meta.type, purpose, verified: false, kind: derivedKind,
      googleTag: GOOGLE_TAG_KEYS.has(fragment.key), uri: meta.uri,
      params: params.map((name) => ({ name, required: required.has(name), label: { fr: name, en: name }, help: { fr: '', en: '' }, pattern: null })),
      cookies: meta.cookies, storage: [], domains: [],
    };
  }
  const needsMarkup = sec ? Boolean(sec.needs_html_markup) : /tarteaucitron\.fallback\s*\(/.test(meta.jsSource);
  entry.needsMarkup = needsMarkup;
  entry.markup = sec ? extractMarkup(sec.snippet ?? '') : '';
  entry.excluded = excluded;
  entry.fallbackClasses = undefined;
  delete entry.fallbackClasses;
  entries.push({ entry, fragment, classes: readFallbackClasses(fragment.code) });
}

for (const key of Object.keys(verified)) {
  if (!fragments.some((f) => f.key === key)) fail(`verified key "${key}" not found in the upstream catalogue.`);
}
for (const key of Object.keys(OTHER_PURPOSE)) {
  const e = entries.find((x) => x.entry.key === key);
  if (!e || e.entry.upstreamType !== 'other') notes.unmappedOtherStale.push(key);
}

// 3. Write catalogue.
const outDir = join(ROOT, 'Resources/catalogue');
const fragDir = join(outDir, 'fragments');
mkdirSync(fragDir, { recursive: true });
for (const f of readdirSync(fragDir)) if (f.endsWith('.js')) rmSync(join(fragDir, f));
const sorted = entries.slice().sort((a, b) => (a.entry.key < b.entry.key ? -1 : 1));
for (const { entry, fragment } of sorted) {
  if (!entry.excluded) writeFileSync(join(fragDir, `${fragment.key}.js`), fragment.code);
}
writeFileSync(join(outDir, 'catalogue.json'), `${JSON.stringify(sorted.map((e) => e.entry), null, 2)}\n`);
writeFileSync(join(outDir, 'REPORT.md'), renderReport({ version: opts.version, sha256, entries: sorted.map((e) => e.entry), notes, section3: Boolean(section3), verified }));

const kept = sorted.filter((e) => !e.entry.excluded).length;
console.log(`catalogue:build OK: ${fragments.length} services, ${kept} fragments written, ${fragments.length - kept} excluded, ${Object.keys(verified).length} verified.`);
