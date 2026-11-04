import test from 'node:test';
import assert from 'node:assert/strict';
import { boot, baseConfig, state } from './_helpers.mjs';

const URL_AUDIT = (phase) => `https://shop.example.com/fr/page.html?ccm_audit=tok&ccm_audit_phase=${phase}`;
const auditConfig = () => ({ ...baseConfig(), mode: 'audit' });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const setup = ({ config = auditConfig(), phase = 'before', cookie, cookies, fragments } = {}) => {
  const sent = [];
  const a = boot({
    config, cookie, cookies, fragments, url: URL_AUDIT(phase), start: false,
    beforeHead: (win) => {
      win.performance.getEntriesByType = () => [
        { name: 'https://shop.example.com/app.js' }, { name: 'https://www.googletagmanager.com/gtag/js?id=G-ABC123DEF4' },
        { name: 'https://www.google-analytics.com/g/collect' }, { name: 'https://www.google-analytics.com/g/collect?x=1' }
      ];
      win.localStorage.setItem('k', '1');
    }
  });
  a.cmp.__parent = () => ({ postMessage: (msg, origin) => sent.push({ msg, origin }) });
  a.cmp.__auditDelay = 10;
  a.doc.body.insertAdjacentHTML('beforeend', '<iframe src="https://www.youtube.com/embed/abc"></iframe><script src="https://www.googletagmanager.com/gtag/js?id=G-ABC123DEF4"></script><script src="https://www.googletagmanager.com/gtm.js?id=GTM-ABCD12"></script>');
  return { a, sent };
};

test('audit : un seul relevé, au format du contrat, vers l origine de la page', async () => {
  const { a, sent } = setup({ cookies: ['_ga=1; Path=/', 'PHPSESSID=x; Path=/'] });
  a.cmp.__start();
  await sleep(120);
  assert.equal(sent.length, 1);
  assert.equal(sent[0].origin, 'https://shop.example.com');
  const m = sent[0].msg;
  assert.deepEqual(Object.keys(m).sort(), ['cookies', 'format', 'google_tags', 'hash', 'hosts', 'iframes', 'mode', 'phase', 'source', 'storage', 'url', 'version']);
  assert.equal(m.source, 'ccm-audit');
  assert.equal(m.phase, 'before');
  assert.equal(m.version, 12);
  assert.equal(m.hash, '9b2e4f0c1a7d');
  assert.equal(m.mode, 'audit');
  assert.equal(m.url, URL_AUDIT('before'));
  assert.deepEqual([...m.hosts], ['www.googletagmanager.com', 'www.google-analytics.com']);
  assert.deepEqual([...m.iframes], ['https://www.youtube.com/embed/abc']);
  assert.deepEqual([...m.cookies].sort(), ['PHPSESSID', '_ga']);
  assert.deepEqual([...m.storage.local], ['k']);
  assert.deepEqual([...m.google_tags.gtag_js], ['G-ABC123DEF4']);
  assert.deepEqual([...m.google_tags.gtm_js], ['GTM-ABCD12']);
});

test('audit : aucun cookie de choix lu ni écrit, aucun POST', async () => {
  const { a, sent } = setup({ cookie: state({ p: { analytics: 1, ads: 0, media: 0, support: 0, personalization: 0 }, s: { gtag: 1 } }) });
  a.cmp.__start();
  assert.equal(a.cmp.hasConsent('gtag'), false, 'cookie ignoré');
  const before = a.doc.cookie;
  a.click('[data-ccm-action="accept_all"]');
  await a.settle();
  await sleep(60);
  assert.equal(a.doc.cookie, before);
  assert.equal(a.calls.length, 0);
  assert.equal(sent.length, 1);
});

test('after_reject : état « Tout refuser » en mémoire, rien d exécuté, pas de cookie', async () => {
  const log = [];
  const f = { gtag: { js() { log.push('gtag'); }, fallback() {} }, 'acme-widget': { js() { log.push('acme'); }, fallback() {} }, matomo: { js() { log.push('matomo'); } }, youtube: { js() {}, fallback() { log.push('yt-fb'); } } };
  const { a, sent } = setup({ phase: 'after_reject', fragments: f });
  Object.keys(f).forEach((k) => { a.win.tarteaucitron.services[k] = { key: k, ...f[k] }; });
  const before = a.doc.cookie;
  a.cmp.__start();
  await sleep(60);
  assert.deepEqual(log, ['yt-fb']);
  assert.equal(a.doc.querySelector('[data-ccm-banner]').hidden, true);
  assert.equal(a.doc.cookie, before);
  assert.equal(a.calls.length, 0);
  assert.equal(sent[0].msg.phase, 'after_reject');
});

for (const mode of ['live', 'preview']) {
  test(`mode ${mode} : rien n est envoyé au parent`, async () => {
    const { a, sent } = setup({ config: { ...baseConfig(), mode }, phase: 'after_reject' });
    a.cmp.__start();
    await sleep(120);
    assert.equal(sent.length, 0);
  });
}
