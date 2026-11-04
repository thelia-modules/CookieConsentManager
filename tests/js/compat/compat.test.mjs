import { test } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import { compatSource } from './_harness.mjs';

function setup(body = '', adapterOverrides = {}) {
  const dom = new JSDOM(`<!doctype html><html><head></head><body>${body}</body></html>`, { url: 'https://shop.example.test/', runScripts: 'outside-only' });
  const { window } = dom;
  const before = new Set(Object.getOwnPropertyNames(window));
  window.eval(compatSource);
  const calls = { placeholder: [], allow: [] };
  const adapter = {
    locale: 'fr',
    user: { gtagUa: 'G-X' },
    isAllowed: () => true,
    placeholderHtml: (k) => { calls.placeholder.push(k); return `<div data-ccm-placeholder-box="${k}"><button type="button" data-ccm-action="service_allow">ok</button></div>`; },
    allow: (k) => calls.allow.push(k),
    log: () => {},
    ...adapterOverrides,
  };
  const t = window.CCMCompat.create(adapter);
  const added = Object.getOwnPropertyNames(window).filter((n) => !before.has(n));
  return { window, document: window.document, t, calls, added };
}

test('window: only CCMCompat is added, create() adds nothing', () => {
  const { added } = setup();
  assert.deepEqual(added, ['CCMCompat']);
});

test('contract surface and frozen parameters', () => {
  const { t } = setup();
  for (const name of ['services', 'user', 'lang', 'job', 'state', 'parameters', 'cookie']) assert.equal(typeof t[name], 'object', name);
  for (const name of ['getElemAttr', 'addScript', 'addInternalScript', 'fallback', 'engage', 'getStyleSize', 'getElemWidth', 'getElemHeight', 'getLocale', 'getLanguage', 'extend', 'fixSelfXSS']) {
    assert.equal(typeof t[name], 'function', name);
  }
  assert.equal(typeof t.cookie.purge, 'function');
  assert.equal(t.isAjax, false);
  assert.deepEqual({ ...t.parameters }, {
    googleConsentMode: false, bingConsentMode: false, piwikConsentMode: false, pianoConsentMode: false, softConsentMode: true, useExternalJs: false,
  });
  assert.ok(Object.isFrozen(t.parameters));
  assert.equal(t.user.gtagUa, 'G-X');
  assert.equal(t.getLanguage(), 'fr');
  assert.equal(t.getLocale(), 'fr_FR');
});

test('getElemAttr: data- prefix, plain attribute, escaping, missing', () => {
  const { t, document } = setup('<div id="e" data-videoID="v1" title="a&quot;&lt;b&gt;\'" width="640"></div>');
  const el = document.getElementById('e');
  assert.equal(t.getElemAttr(el, 'videoID'), 'v1');
  assert.equal(t.getElemAttr(el, 'data-videoID'), 'v1');
  assert.equal(t.getElemAttr(el, 'width'), '640');
  assert.equal(t.getElemAttr(el, 'title'), 'a&quot;&lt;b&gt;&#039;');
  assert.equal(t.getElemAttr(el, 'absent'), '');
});

test('getElemAttr: url attributes accept only http(s); srcdoc read from the plain attribute', () => {
  const { t, document } = setup('<div id="e" data-url="javascript:alert(1)" data-src="https://a.test/x" srcdoc="&lt;p&gt;x&lt;/p&gt;" data-srcdoc="ignored"></div><div id="f" data-url="https://ok.test/p?q=1"></div><div id="g"></div>');
  const el = document.getElementById('e');
  assert.equal(t.getElemAttr(el, 'url'), '');
  assert.equal(t.getElemAttr(el, 'data-url'), '');
  assert.equal(t.getElemAttr(el, 'data-src'), 'https://a.test/x');
  assert.equal(t.getElemAttr(document.getElementById('f'), 'url'), 'https://ok.test/p?q=1');
  assert.equal(t.getElemAttr(document.getElementById('g'), 'url'), '');
  assert.equal(t.getElemAttr(el, 'srcdoc'), '&lt;p&gt;x&lt;/p&gt;');
  assert.equal(t.getElemAttr(el, 'data-srcdoc'), '&lt;p&gt;x&lt;/p&gt;');
});

test('getStyleSize', () => {
  const { t } = setup();
  assert.equal(t.getStyleSize(null), 'auto');
  assert.equal(t.getStyleSize('640'), '640px');
  assert.equal(t.getStyleSize('50%'), '50%');
  assert.equal(t.getStyleSize(' 12.5rem '), '12.5rem');
  assert.equal(t.getStyleSize('calc(1px)'), 'auto');
  assert.equal(t.getStyleSize('10px;color:red'), 'auto');
});

test('fallback: function content replaces innerHTML, sizes applied through getElemAttr', () => {
  const { t, document } = setup('<div class="a" data-width="300" height="50%"></div><div class="b"></div><div class="c"></div>');
  t.fallback(['a', 'b'], (el) => `<i>${el.className}</i>`);
  const [a, b, c] = ['a', 'b', 'c'].map((cls) => document.querySelector(`.${cls}`));
  assert.equal(a.innerHTML, '<i>a</i>');
  assert.equal(b.innerHTML, '<i>b</i>');
  assert.equal(c.innerHTML, '');
  assert.equal(a.style.width, '300px');
  assert.equal(a.style.height, '50%');
  assert.equal(b.style.width, '');
});

test('fallback: noInner=true hands the element over without touching innerHTML; string content is set as is', () => {
  const { t, document } = setup('<div class="a">keep</div><div class="s">old</div>');
  let seen = null;
  t.fallback(['a'], (el) => { seen = el; return 'ignored'; }, true);
  assert.equal(document.querySelector('.a').innerHTML, 'keep');
  assert.equal(seen, document.querySelector('.a'));
  t.fallback(['s'], '');
  assert.equal(document.querySelector('.s').innerHTML, '');
  t.fallback(['missing'], () => { throw new Error('must not be called'); });
});

test('getElemWidth / getElemHeight: attribute first, then client size', () => {
  const { t, document } = setup('<div id="e" data-width="200"></div><div id="f"></div>');
  assert.equal(t.getElemWidth(document.getElementById('e')), '200');
  assert.equal(t.getElemHeight(document.getElementById('f')), 0);
});

test('engage: returns the adapter placeholder for the service id', () => {
  const { t, calls } = setup();
  const html = t.engage('youtube');
  assert.deepEqual(calls.placeholder, ['youtube']);
  assert.ok(html.includes('data-ccm-placeholder-box="youtube"'));
});

test('allow click: a service_allow button inside a placeholder box calls adapter.allow(key) once and stops propagation', () => {
  const { t, document, window, calls } = setup('<div id="host"></div>');
  document.getElementById('host').innerHTML = t.engage('vimeo');
  let reachedBubble = 0;
  document.addEventListener('click', () => { reachedBubble += 1; });
  document.querySelector('[data-ccm-action="service_allow"]').dispatchEvent(new window.MouseEvent('click', { bubbles: true, cancelable: true }));
  assert.deepEqual(calls.allow, ['vimeo']);
  assert.equal(reachedBubble, 0);
  const other = document.createElement('button');
  other.setAttribute('data-ccm-action', 'service_allow');
  document.body.appendChild(other);
  other.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
  assert.deepEqual(calls.allow, ['vimeo'], 'a button outside a placeholder box is left to the engine');
});

test('addScript: async script in head with id, attribute and onload callback (once)', () => {
  const { t, document } = setup();
  let called = 0;
  t.addScript('https://cdn.test/a.js', 'sid', () => { called += 1; }, true, 'defer', true);
  const s = document.head.querySelector('script[src="https://cdn.test/a.js"]');
  assert.ok(s);
  assert.equal(s.id, 'sid');
  assert.equal(s.async, true);
  assert.equal(s.getAttribute('defer'), 'true');
  s.onload();
  s.onload();
  assert.equal(called, 1);
});

test('addScript: execute=false only runs the callback; addInternalScript behaves the same (useExternalJs=false)', () => {
  const { t, document } = setup();
  let called = 0;
  t.addScript('https://cdn.test/b.js', '', () => { called += 1; }, false);
  assert.equal(called, 1);
  assert.equal(document.head.querySelector('script'), null);
  t.addInternalScript('https://cdn.test/c.js');
  assert.ok(document.head.querySelector('script[src="https://cdn.test/c.js"]'));
});

test('cookie.purge: exact names and prefix patterns', () => {
  const { t, document } = setup();
  document.cookie = '_pk_id.1=a; path=/';
  document.cookie = '_pk_ses.1=b; path=/';
  document.cookie = 'keep=c; path=/';
  t.cookie.purge(['_pk_*']);
  assert.equal(document.cookie, 'keep=c');
  t.cookie.purge(['keep']);
  assert.equal(document.cookie, '');
});

test('extend and fixSelfXSS', () => {
  const { t } = setup();
  const a = { x: 1 };
  t.extend(a, { y: 2 });
  assert.deepEqual(a, { x: 1, y: 2 });
  assert.equal(t.fixSelfXSS('<a href="x">&\'</a>'), '&lt;a href=&quot;x&quot;&gt;&amp;&#039;&lt;/a&gt;');
});

test('re-create: only the latest adapter receives allow()', () => {
  const first = setup('');
  const second = first.window.CCMCompat.create({ locale: 'en', user: {}, placeholderHtml: () => '', allow: (k) => second.allowed.push(k) });
  second.allowed = [];
  first.document.body.innerHTML = '<div data-ccm-placeholder-box="k"><button data-ccm-action="service_allow"></button></div>';
  first.document.querySelector('button').dispatchEvent(new first.window.MouseEvent('click', { bubbles: true }));
  assert.deepEqual(first.calls.allow, []);
  assert.deepEqual(second.allowed, ['k']);
});

test('H4: the fragments read their words from the adapter (language of the visitor), English without them', () => {
  const german = setup('', { lang: { allow: 'Zulassen', fallback: 'ist deaktiviert.' } });
  assert.deepEqual({ ...german.t.lang }, { allow: 'Zulassen', fallback: 'ist deaktiviert.' });
  assert.deepEqual({ ...setup().t.lang }, { allow: 'Allow', fallback: 'is disabled.' });
});
