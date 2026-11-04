import test from 'node:test';
import assert from 'node:assert/strict';
import { boot, baseConfig, state, cookieValue, decodeCookie } from './_helpers.mjs';

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const media = () => state({ p: { analytics: 0, ads: 0, media: 1, support: 0, personalization: 0 }, s: { gtag: 0, googletagmanager: 0, youtube: 1, 'acme-widget': 0, 'tiktok-via-gtm': 0 } });
const turbo = (a) => a.doc.dispatchEvent(new a.win.Event('turbo:load'));
const modalConfig = () => { const c = baseConfig(); c.options.layout = 'modal'; return c; };
const sheetMedia = (matches) => (win) => { win.matchMedia = (q) => ({ matches: matches && /max-width/.test(q), media: q, addEventListener() {}, removeEventListener() {} }); };

test('après le turbo:load initial, retirer un media rend l espace réservé sans rechargement', async () => {
  const a = boot({ cookie: media(), fragments: { youtube: { js() {}, fallback() {} } } });
  assert.ok(a.doc.querySelector('iframe'));
  turbo(a);
  a.click('[data-ccm-action="reject_all"]');
  await a.settle();
  assert.equal(a.doc.querySelector('iframe'), null);
  assert.ok(a.doc.querySelector('[data-ccm-placeholder="youtube"]'));
  assert.equal(a.win.__reloaded, undefined);
});

test('le piège de focus de la modale survit au turbo:load initial', () => {
  const a = boot({ config: modalConfig() });
  turbo(a);
  const buttons = [...a.doc.querySelectorAll('[data-ccm-banner] button')];
  buttons[buttons.length - 1].focus();
  const ev = new a.win.KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
  a.doc.activeElement.dispatchEvent(ev);
  assert.equal(ev.defaultPrevented, true);
  assert.equal(a.doc.activeElement, buttons[0]);
});

test('sur petit écran la modale devient une feuille basse, non bloquante, sans piège ni aria-modal', () => {
  const a = boot({ config: modalConfig(), beforeHead: sheetMedia(true) });
  const banner = a.doc.querySelector('[data-ccm-banner]');
  assert.equal(banner.getAttribute('aria-modal'), 'false');
  const buttons = [...banner.querySelectorAll('button')];
  buttons[buttons.length - 1].focus();
  const ev = new a.win.KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
  a.doc.activeElement.dispatchEvent(ev);
  assert.equal(ev.defaultPrevented, false);
  const wide = boot({ config: modalConfig(), beforeHead: sheetMedia(false) });
  assert.equal(wide.doc.querySelector('[data-ccm-banner]').getAttribute('aria-modal'), 'true');
});

test('après un choix dans la bannière, le focus va au lien de réouverture', async () => {
  const a = boot();
  a.doc.body.insertAdjacentHTML('beforeend', '<a href="#ccm-preferences" id="reopen">Cookies</a>');
  a.doc.querySelector('[data-ccm-action="accept_all"]').focus();
  a.click('[data-ccm-action="accept_all"]');
  await a.settle();
  assert.equal(a.doc.activeElement.id, 'reopen');
});

test('l iframe débloquée reste dans l ordre de tabulation et porte un titre', async () => {
  const a = boot({ fragments: { youtube: { js() {}, fallback() {} } } });
  a.doc.querySelector('#vid').removeAttribute('data-ccm-attr-title');
  a.doc.querySelector('#vid').innerHTML = '<div data-ccm-placeholder-box><button id="al" data-ccm-action="service_allow" data-ccm-service="youtube">Allow</button></div>';
  a.click('#al');
  await a.settle();
  const f = a.doc.querySelector('iframe');
  assert.ok(f);
  assert.notEqual(f.getAttribute('tabindex'), '-1');
  assert.equal(f.getAttribute('title'), 'YouTube');
  assert.equal(a.doc.activeElement, f);
});

test('un choix hors ligne est rejoué avec sa source et son action d origine', async () => {
  const a = boot({ fetchOpts: { fail: true } });
  a.click('[data-ccm-banner] [data-ccm-action="accept_all"]');
  await a.settle();
  const m = a.doc.cookie.match(/ccm_consent=([^;]*)/);
  assert.equal(decodeCookie(m[1]).pd, 1);
  const b = boot({ cookie: m[1] });
  await b.settle();
  assert.equal(b.calls.length, 1);
  assert.equal(b.calls[0].body.source, 'banner');
  assert.equal(b.calls[0].body.action, 'accept_all');
});

test('un cookie sans source (ancien format) est rejoué comme avant', async () => {
  const b = boot({ cookie: state({ pd: 1 }) });
  await b.settle();
  assert.equal(b.calls[0].body.source, 'preferences');
});

test('en aperçu, aucun service exécuté, aucune iframe, scripts balisés inertes, bandeau Aperçu visible', async () => {
  const log = [];
  const g = { js() { log.push('js'); }, fallback() {} };
  const a = boot({ config: { ...baseConfig(), mode: 'preview' }, messages: { preview: 'Aperçu du brouillon : simulation.' }, fragments: { gtag: g, youtube: g, 'acme-widget': g } });
  const note = a.doc.querySelector('[data-ccm-preview-note]');
  assert.ok(note);
  assert.match(note.textContent, /Aperçu/);
  a.click('[data-ccm-banner] [data-ccm-action="accept_all"]');
  await a.settle();
  assert.deepEqual(log, []);
  assert.equal(a.doc.querySelector('iframe'), null);
  assert.ok(a.doc.querySelector('script[type="text/plain"][data-ccm-service="acme-widget"]'));
  assert.equal(a.win.__acme, undefined);
  assert.equal(a.cmp.hasConsent('gtag'), true, 'le choix simulé reste lisible');
  assert.equal(a.doc.querySelectorAll('[data-ccm-preview-note]').length, 1);
});

test('le relevé d audit retient les ressources vues par l observateur jusqu à l envoi', async () => {
  const sent = [];
  let observer = null;
  const a = boot({
    config: { ...baseConfig(), mode: 'audit' }, start: false, url: 'https://shop.example.com/fr/page.html?ccm_audit=tok&ccm_audit_phase=before',
    beforeHead: (win) => {
      win.performance.getEntriesByType = () => [];
      win.PerformanceObserver = class { constructor(cb) { observer = cb; } observe(o) { this.opts = o; observer.opts = o; } };
    }
  });
  a.cmp.__parent = () => ({ postMessage: (msg) => sent.push(msg) });
  a.cmp.__auditDelay = 30;
  assert.ok(observer, 'observateur posé dès le chargement du moteur');
  assert.equal(observer.opts.type, 'resource');
  assert.equal(observer.opts.buffered, true);
  a.cmp.__start();
  observer({ getEntries: () => [{ name: 'https://fonts.gstatic.com/s/roboto.woff2' }, { name: 'https://fonts.googleapis.com/css2' }] });
  await sleep(120);
  assert.deepEqual([...sent[0].hosts].sort(), ['fonts.googleapis.com', 'fonts.gstatic.com']);
});

test('un espace réservé sans src est remplacé par le rendu du service après Autoriser', async () => {
  const a = boot({ fragments: { youtube: { js() { a.doc.querySelectorAll('.youtube_player').forEach((e) => { e.innerHTML = '<iframe src="https://www.youtube-nocookie.com/embed/abc"></iframe>'; }); }, fallback() {} } } });
  a.doc.querySelector('#vid').remove();
  a.doc.body.insertAdjacentHTML('beforeend', '<div id="ph" data-ccm-placeholder="youtube" data-ccm-attr-class="youtube_player" data-ccm-attr-videoid="abc"><div data-ccm-placeholder-box><button id="al" data-ccm-action="service_allow" data-ccm-service="youtube">Allow</button></div></div>');
  a.cmp.__start && a.doc.querySelector('#al');
  a.click('#al');
  await a.settle();
  const ph = a.doc.querySelector('#ph');
  assert.equal(ph.querySelector('[data-ccm-placeholder-box]'), null);
  assert.ok(ph.querySelector('iframe'));
  assert.ok(ph.classList.contains('youtube_player'));
});

test('sans élément amont, le bloc est au moins vidé et l événement est émis', async () => {
  const a = boot({ fragments: { youtube: { js() {}, fallback() {} } } });
  a.doc.querySelector('#vid').remove();
  a.doc.body.insertAdjacentHTML('beforeend', '<div id="ph" data-ccm-placeholder="youtube"><div data-ccm-placeholder-box><button id="al" data-ccm-action="service_allow" data-ccm-service="youtube">Allow</button></div></div>');
  let fired = 0;
  a.doc.addEventListener('ccm:service:youtube:allowed', () => { fired++; });
  a.click('#al');
  await a.settle();
  assert.equal(a.doc.querySelector('#ph [data-ccm-placeholder-box]'), null);
  assert.equal(fired, 1);
});

test('la réserve de défilement suit la hauteur de la bannière non bloquante', () => {
  const a = boot();
  const dialog = a.doc.querySelector('[data-ccm-banner]');
  Object.defineProperty(dialog, 'offsetHeight', { configurable: true, get: () => 180 });
  a.click('[data-ccm-action="open"]');
  a.doc.querySelector('[data-ccm-panel] [data-ccm-action="close"]').click();
  assert.equal(a.doc.documentElement.style.getPropertyValue('--ccm-reserve'), '180px');
  assert.ok(a.doc.documentElement.classList.contains('ccm-reserve'));
});

test('après le choix la réserve est retirée', async () => {
  const a = boot();
  a.click('[data-ccm-action="accept_all"]');
  await a.settle();
  assert.equal(a.doc.documentElement.classList.contains('ccm-reserve'), false);
  assert.equal(a.doc.documentElement.style.getPropertyValue('--ccm-reserve'), '');
});

test('le relevé d audit ajoute l hôte des polices chargées par une feuille de style externe (absent de Resource Timing)', async () => {
  const sent = [];
  const a = boot({
    config: { ...baseConfig(), mode: 'audit' }, start: false, url: 'https://shop.example.com/fr/page.html?ccm_audit=tok&ccm_audit_phase=before',
    beforeHead: (win) => { win.performance.getEntriesByType = () => [{ name: 'https://fonts.googleapis.com/css2?family=Roboto' }]; }
  });
  a.doc.head.insertAdjacentHTML('beforeend', '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto"><link rel="stylesheet" href="/local.css">');
  const asked = [];
  a.win.fetch = (url) => { asked.push(url); return Promise.resolve({ text: async () => "@font-face{font-family:'Roboto';src:url(https://fonts.gstatic.com/s/roboto.woff2) format('woff2')}@font-face{font-family:'Unused';src:url(https://other.example.net/u.woff2)}" }); };
  Object.defineProperty(a.doc, 'fonts', { value: { forEach: (cb) => [{ family: '"Roboto"', status: 'loaded' }, { family: 'Unused', status: 'unloaded' }].forEach(cb) } });
  a.cmp.__parent = () => ({ postMessage: (msg) => sent.push(msg) });
  a.cmp.__auditDelay = 10;
  a.cmp.__start();
  await sleep(120);
  assert.deepEqual(asked, ['https://fonts.googleapis.com/css2?family=Roboto']);
  assert.deepEqual([...sent[0].hosts].sort(), ['fonts.googleapis.com', 'fonts.gstatic.com']);
  assert.equal(sent.length, 1);
});
