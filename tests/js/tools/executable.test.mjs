// A parameter is "executable" when its value decides what script runs on the storefront (origin of a loaded script,
// inline code, markup injected into the page). The catalogue carries the flag; these tests make it verifiable.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { deriveExecutable, TAG_MANAGER_CONTAINERS } from '../../../tools/catalogue/executable.mjs';
import { readParams } from '../../../tools/catalogue/sandbox.mjs';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');
const catalogue = JSON.parse(readFileSync(join(ROOT, 'Resources/catalogue/catalogue.json'), 'utf8'));
const fragmentOf = (key) => readFileSync(join(ROOT, 'Resources/catalogue/fragments', `${key}.js`), 'utf8');

function service(body, fallback = '') {
  return `tarteaucitron.services.probe = { "key": "probe", "type": "api", "name": "Probe", "needConsent": true, "cookies": [],
    "js": function () { ${body} }, "fallback": function () { ${fallback} } };`;
}

function derive(body, params, fallback = '') {
  return [...deriveExecutable(service(body, fallback), 'probe', params).executable].sort();
}

test('a literal host followed by the parameter is not executable', () => {
  assert.deepEqual(derive("tarteaucitron.addScript('https://cdn.example.test/lib.js?id=' + tarteaucitron.user.accountId);", ['accountId']), []);
});

test('the parameter as the whole address, or at the start of it, is executable', () => {
  assert.deepEqual(derive('tarteaucitron.addScript(tarteaucitron.user.scriptUrl);', ['scriptUrl']), ['scriptUrl']);
  assert.deepEqual(derive("tarteaucitron.addScript(tarteaucitron.user.host + 'piwik.js');", ['host']), ['host']);
});

test('the parameter inside the host part is executable', () => {
  assert.deepEqual(derive("tarteaucitron.addScript('https://' + tarteaucitron.user.sub + '.example.test/w.js');", ['sub']), ['sub']);
  assert.deepEqual(derive("tarteaucitron.addScript('//tracker.example.' + tarteaucitron.user.tld + '/t.js');", ['tld']), ['tld']);
});

test('the parameter reaches the host through a local variable', () => {
  const body = "var domain = 'www.example.test'; if (tarteaucitron.user.custom) { domain = tarteaucitron.user.custom; } tarteaucitron.addScript('https://' + domain + '/gtag/js?id=' + tarteaucitron.user.id);";
  assert.deepEqual(derive(body, ['custom', 'id']), ['custom']);
});

test('the parameter reaches the address through a script element built by hand', () => {
  const body = "var s = document.createElement('script'); s.src = tarteaucitron.user.source; document.head.appendChild(s);";
  assert.deepEqual(derive(body, ['source']), ['source']);
});

test('the parameter written in the body of an inline script is executable', () => {
  const body = "var s = document.createElement('script'); s.text = 'var id=\"' + tarteaucitron.user.identifier + '\";'; document.body.appendChild(s);";
  assert.deepEqual(derive(body, ['identifier']), ['identifier']);
});

test('the parameter set as an event handler attribute of a script is executable', () => {
  assert.deepEqual(derive("tarteaucitron.addScript('https://cdn.example.test/a.js', '', null, true, 'onload', tarteaucitron.user.handler);", ['handler']), ['handler']);
});

test('the parameter used as a data attribute of a literal script is not executable', () => {
  assert.deepEqual(derive("tarteaucitron.addScript('https://cdn.example.test/a.js', '', null, true, 'data-account', tarteaucitron.user.account);", ['account']), []);
});

test('the parameter that only feeds a call to the vendor library is not executable', () => {
  assert.deepEqual(derive('window.dataLayer = window.dataLayer || []; window.dataLayer.push(tarteaucitron.user.token);', ['token']), []);
});

test('the parameter written as markup into the page is executable', () => {
  assert.deepEqual(derive("var d = document.createElement('div'); d.innerHTML = '<img src=\"https://p.example.test/h?s=' + tarteaucitron.user.site + '\">'; document.body.appendChild(d);", ['site']), ['site']);
});

test('the parameter loaded only in fallback() is found', () => {
  assert.deepEqual(derive('', ['hostUrl'], "tarteaucitron.addScript(tarteaucitron.user.hostUrl);"), ['hostUrl']);
});

test('a parameter loaded as a script only when softConsentMode is off is found', () => {
  const fallback = 'if (tarteaucitron.parameters.softConsentMode === true) { return; } tarteaucitron.addScript(tarteaucitron.user.lateUrl);';
  assert.deepEqual(derive('', ['lateUrl'], fallback), ['lateUrl']);
});

test('the parameter loaded from the callback of another script is found', () => {
  const body = "tarteaucitron.addScript('https://cdn.example.test/a.js', '', function () { tarteaucitron.addScript(tarteaucitron.user.second); });";
  assert.deepEqual(derive(body, ['second']), ['second']);
});

test('a branch the probe cannot enter is still read from the source', () => {
  const body = "if (window.__never === 42) { tarteaucitron.addScript(tarteaucitron.user.hidden); }";
  assert.deepEqual(derive(body, ['hidden']), ['hidden']);
});

test('the flags written in catalogue.json: the ones the audit named, and ones that must stay plain identifiers', () => {
  const flagged = (key, name) => catalogue.find((e) => e.key === key).params.find((p) => p.name === name).executable;
  for (const [key, name] of [
    ['matomotm', 'matomotmUrl'], ['matomo', 'matomoHost'], ['atinternet', 'atLibUrl'], ['dynatrace', 'dynatraceJSPath'],
    ['posthog', 'posthogHost'], ['gtag', 'gtagCustomDomain'], ['googletagmanager', 'googletagmanagerCustomDomain'], ['piwikpro', 'piwikProContainer'],
    ['googletagmanager', 'googletagmanagerId'], ['multiplegoogletagmanager', 'multiplegoogletagmanagerId'], ['tagcommander', 'tagcommanderid'],
  ]) {
    assert.equal(flagged(key, name), true, `${key}.${name}`);
  }
  for (const [key, name] of [['gtag', 'gtagUa'], ['clarity', 'clarity'], ['abtasty', 'abtastyID'], ['facebookpixel', 'facebookpixelId'], ['hotjar', 'hotjarId']]) {
    assert.equal(flagged(key, name), false, `${key}.${name}`);
  }
});

test('every parameter of the catalogue carries a boolean flag, and it is what the fragments give', () => {
  const wrong = [];
  for (const entry of catalogue) {
    for (const param of entry.params) {
      if (typeof param.executable !== 'boolean') wrong.push(`${entry.key}.${param.name}: no flag`);
    }
    if (entry.excluded) continue;
    const { executable } = deriveExecutable(fragmentOf(entry.key), entry.key, entry.params.map((p) => p.name));
    for (const param of entry.params) {
      if (param.executable !== executable.has(param.name)) wrong.push(`${entry.key}.${param.name}: catalogue ${param.executable}, fragment ${executable.has(param.name)}`);
    }
  }
  assert.deepEqual(wrong, [], 'run node tools/catalogue/build.mjs to regenerate (see docs/maintaining-the-catalogue.md)');
});

test('sabotage: a fragment that loads a parameter as a script, unflagged, is caught by the same comparison', () => {
  const original = fragmentOf('clarity');
  const sabotaged = original.replace(/tarteaucitron\.addScript\('https:\/\/www\.clarity\.ms\/tag\/' \+ tarteaucitron\.user\.clarity/, 'tarteaucitron.addScript(tarteaucitron.user.clarity');
  assert.notEqual(sabotaged, original, 'anchor not found');
  const params = readParams(original).params;
  assert.equal(deriveExecutable(original, 'clarity', params).executable.has('clarity'), false);
  assert.equal(deriveExecutable(sabotaged, 'clarity', params).executable.has('clarity'), true);
});

test('each read stands on its own: the probe finds a minified address, the source read finds the branch the probe cannot enter', () => {
  const minified = "(function (host) { var s = document.createElement('script'); s.src = host + '/static/array.js'; document.head.appendChild(s); })(tarteaucitron.user.apiHost);";
  const never = 'if (window.__never === 42) { tarteaucitron.addScript(tarteaucitron.user.hidden); }';
  const only = (reads, body, name) => [...deriveExecutable(service(body), 'probe', [name], { reads }).executable];
  assert.deepEqual(only(['dynamic'], minified, 'apiHost'), ['apiHost']);
  assert.deepEqual(only(['static'], minified, 'apiHost'), []);
  assert.deepEqual(only(['dynamic'], never, 'hidden'), []);
  assert.deepEqual(only(['static'], never, 'hidden'), ['hidden']);
});

test('a parameter whose fragment appends a literal-host address after a literal prefix variable stays plain', () => {
  const body = "var url = 'https://www.example.test/api.js?onload=x'; if (tarteaucitron.user.key !== undefined) { url += '&render=' + tarteaucitron.user.key; } tarteaucitron.addScript(url);";
  assert.deepEqual(derive(body, ['key']), []);
});

test('a tag manager container ID is executable although it follows a literal host: the container runs code its owner writes', () => {
  const body = "tarteaucitron.addScript('https://tags.example.test/gtm.js?id=' + tarteaucitron.user.containerId);";
  assert.deepEqual(derive(body, ['containerId']), [], 'the generic rule leaves it plain');
  assert.deepEqual([...deriveExecutable(fragmentOf('googletagmanager'), 'googletagmanager', ['googletagmanagerId']).executable], ['googletagmanagerId']);
});

test('every listed tag manager container is a parameter its fragment reads', () => {
  const missing = [];
  for (const [key, names] of Object.entries(TAG_MANAGER_CONTAINERS)) {
    const entry = catalogue.find((e) => e.key === key);
    for (const name of names) {
      if (!entry || !entry.params.some((p) => p.name === name)) missing.push(`${key}.${name}`);
    }
  }
  assert.deepEqual(missing, [], 'a renamed upstream parameter would silently drop the flag');
});
