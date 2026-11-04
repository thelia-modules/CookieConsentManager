#!/usr/bin/env node
// Maintainer tool (Node 18, no network): derives Resources/catalogue/embed-rules.json, the embed endpoints (host and path prefixes)
// of the media services, from the vendored fragments. The server uses it to turn an iframe of the theme into a placeholder.
//
//   node tools/catalogue/embed-rules.mjs          write the file
//   node tools/catalogue/embed-rules.mjs --check  fail when the file is not what the fragments give
//
// A fragment tells where its iframe points either with a literal address (src="https://host/path..."), or with a variable that the
// fragment assigns a literal address to. When the address only comes from what the editor types (generic iframe, user-supplied URL),
// nothing is derived and the service is listed under "undetectable" with the reason.
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const TARGET = join(ROOT, 'Resources/catalogue/embed-rules.json');
const ADDRESS = /^(?:https?:)?\/\/([a-z0-9][a-z0-9.-]*\.[a-z]{2,})((?:\/[^'"\s\\?&#<>{}]*)?)/i;
// The privacy and terms pages of the vendor also appear as literals, never as an iframe source.
const NOT_AN_EMBED = /(?:privacy|policies?|terms|conditions|donnees|protection|about|help|legal)/i;

/** @returns {{ rules: Record<string, Record<string, string[]>>, undetectable: Record<string, string> }} */
export function derive(catalogue, fragmentsDirectory) {
  const rules = {};
  const undetectable = {};
  for (const entry of catalogue.filter((e) => e.kind === 'media').sort((a, b) => a.key.localeCompare(b.key))) {
    const file = join(fragmentsDirectory, `${entry.key}.js`);
    const code = existsSync(file) ? readFileSync(file, 'utf8') : '';
    if (!/<iframe/i.test(code)) {
      undetectable[entry.key] = existsSync(file) ? 'the fragment builds no iframe (script, widget or markup driven)' : 'no fragment';
      continue;
    }
    const found = new Map();
    for (const address of addresses(code)) {
      const match = ADDRESS.exec(address);
      if (!match || NOT_AN_EMBED.test(match[2])) continue;
      const host = match[1].toLowerCase();
      const prefix = match[2] === '' ? '/' : match[2];
      if (!found.has(host)) found.set(host, new Set());
      found.get(host).add(prefix);
    }
    if (found.size === 0) {
      undetectable[entry.key] = 'the iframe address is typed by the editor, the fragment holds no fixed host';
      continue;
    }
    rules[entry.key] = Object.fromEntries([...found.entries()].sort(([a], [b]) => a.localeCompare(b)).map(([host, prefixes]) => [host, minimal([...prefixes])]));
  }

  return { rules, undetectable };
}

// A prefix covered by a shorter one of the same host adds nothing.
function minimal(prefixes) {
  const sorted = prefixes.sort((a, b) => a.length - b.length || a.localeCompare(b));
  const kept = [];
  for (const prefix of sorted) {
    if (!kept.some((short) => prefix.startsWith(short))) kept.push(prefix);
  }

  return kept.sort();
}

function* addresses(code) {
  const tag = /<iframe\b/gi;
  let at;
  while ((at = tag.exec(code))) {
    const window = code.slice(at.index, at.index + 900);
    const source = /\bsrc=\\?["']?/i.exec(window);
    if (!source) continue;
    const after = window.slice(source.index + source[0].length);
    const literal = /^[^'"\\]*/.exec(after)[0];
    if (/^(?:https?:)?\/\//i.test(literal)) {
      yield literal;
      continue;
    }
    // The source is a variable (` + url + `) or a call: follow the literal addresses assigned to it.
    const variable = /^\W*\+\s*([A-Za-z_$][\w$]*)\s*(?:\+|$|[;)])/.exec(after.slice(literal.length)) ?? /^[^A-Za-z_$]*([A-Za-z_$][\w$]*)/.exec(after.slice(literal.length));
    if (!variable) continue;
    const assignment = new RegExp(`\\b${variable[1].replace(/\$/g, '\\$')}\\s*\\+?=\\s*(?:\\(?\\s*)?['"]((?:https?:)?//[^'"]+)['"]`, 'g');
    let assigned;
    while ((assigned = assignment.exec(code))) yield assigned[1];
  }
}

const catalogue = JSON.parse(readFileSync(join(ROOT, 'Resources/catalogue/catalogue.json'), 'utf8'));
const output = `${JSON.stringify(derive(catalogue, join(ROOT, 'Resources/catalogue/fragments')), null, 2)}\n`;
if (process.argv.includes('--check')) {
  if (!existsSync(TARGET) || readFileSync(TARGET, 'utf8') !== output) {
    console.error('embed-rules.json is not what the fragments give: run node tools/catalogue/embed-rules.mjs');
    process.exit(1);
  }
} else {
  writeFileSync(TARGET, output);
}
