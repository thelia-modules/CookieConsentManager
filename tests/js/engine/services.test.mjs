import test from 'node:test';
import assert from 'node:assert/strict';
import { boot, state, baseConfig } from './_helpers.mjs';

const frag = (log, k) => ({ js() { log.push(k + ':js'); }, fallback() { log.push(k + ':fb'); } });
const mkFrags = (log) => ({ gtag: frag(log, 'gtag'), googletagmanager: frag(log, 'gtm'), youtube: frag(log, 'yt') });

test('GTM non chargé avec seulement personalization accordé, chargé avec ads', () => {
  const log = [];
  boot({ cookie: state({ p: { analytics: 0, ads: 0, media: 0, support: 0, personalization: 1 }, s: { gtag: 0, googletagmanager: 1, youtube: 0, 'acme-widget': 0, 'tiktok-via-gtm': 0 } }), fragments: mkFrags(log) });
  assert.ok(!log.includes('gtm:js'), log.join());
  const log2 = [];
  boot({ cookie: state({ p: { analytics: 0, ads: 1, media: 0, support: 0, personalization: 0 }, s: { gtag: 0, googletagmanager: 1, youtube: 0, 'acme-widget': 0, 'tiktok-via-gtm': 1 } }), fragments: mkFrags(log2) });
  assert.ok(log2.includes('gtm:js'));
  assert.ok(!log2.includes('gtag:js'));
});

test('mode basique : rien avant choix ; fallback des media ; mode avancé : js() des googleTag avant choix', () => {
  const log = [];
  boot({ fragments: mkFrags(log) });
  assert.deepEqual(log, ['yt:fb']);
  const config = baseConfig();
  config.gcm.mode = 'advanced';
  const log2 = [];
  boot({ config, fragments: mkFrags(log2) });
  assert.ok(log2.includes('gtag:js') && log2.includes('gtm:js'));
  assert.ok(!log2.includes('gtag:fb'));
});

test('accept_all : js() de chaque service accordé, une seule fois', async () => {
  const log = [];
  const a = boot({ fragments: mkFrags(log) });
  a.click('[data-ccm-action="accept_all"]');
  await a.settle();
  a.click('[data-ccm-action="accept_all"]');
  assert.equal(log.filter((x) => x === 'gtag:js').length, 1);
  assert.equal(log.filter((x) => x === 'gtm:js').length, 1);
});

test('scripts balisés text/plain réactivés au consentement du service', async () => {
  const a = boot({ fragments: mkFrags([]) });
  assert.equal(a.win.__acme, undefined);
  a.click('[data-ccm-action="accept_all"]');
  await a.settle();
  assert.equal(a.win.__acme, 1);
  assert.equal(a.doc.querySelector('script[data-ccm-active="acme-widget"]').type, '');
});

test('API : onConsent immédiat si accordé, file avant ready, onChange', async () => {
  const a = boot({ cookie: state({ p: { analytics: 1, ads: 0, media: 0, support: 0, personalization: 0 }, s: { gtag: 1, googletagmanager: 0, youtube: 0, 'acme-widget': 0, 'tiktok-via-gtm': 0 } }), fragments: mkFrags([]), start: false });
  const seen = [];
  a.cmp.onConsent('gtag', () => seen.push('gtag'));
  a.win.ccmQueue.push(() => seen.push('queue'));
  assert.deepEqual(seen, [], 'avant ready : en file');
  a.cmp.__start();
  assert.deepEqual(seen.sort(), ['gtag', 'queue']);
  const changes = [];
  a.cmp.onChange((s) => changes.push(s));
  let later = 0;
  a.cmp.onConsent('youtube', () => later++);
  await a.cmp.setConsent({ services: { youtube: 1 } });
  assert.equal(later, 1);
  assert.equal(changes.length, 1);
  assert.equal(a.calls[0].body.source, 'api');
  assert.equal(a.cmp.hasConsent('youtube'), true);
  assert.equal(a.cmp.hasPurposeConsent('media'), true);
});

test('accessibilité : Échap ferme le panneau et restitue le focus ; aria-checked ; focus piégé', () => {
  const a = boot({ fragments: mkFrags([]) });
  const opener = a.doc.querySelector('[data-ccm-action="open"]');
  opener.focus();
  a.click('[data-ccm-action="open"]');
  const panel = a.doc.querySelector('[data-ccm-panel]');
  assert.equal(panel.hidden, false);
  assert.equal(panel.getAttribute('aria-modal'), 'true');
  assert.equal(a.doc.querySelector('[data-ccm-banner]').hidden, true, 'bannière masquée pendant le panneau au premier passage');
  const sw = a.doc.querySelector('[role="switch"][data-ccm-purpose="analytics"]');
  a.click('[role="switch"][data-ccm-purpose="analytics"]');
  assert.equal(sw.getAttribute('aria-checked'), 'true');
  assert.equal(a.doc.querySelector('[role="switch"][data-ccm-service="gtag"]').getAttribute('aria-checked'), 'true');
  const sw2 = a.doc.querySelectorAll('button[data-ccm-action]');
  const last = [...panel.querySelectorAll('button')].pop();
  last.focus();
  const ev = new a.win.KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
  last.dispatchEvent(ev);
  assert.equal(ev.defaultPrevented, true);
  assert.equal(a.doc.activeElement, panel.querySelector('button'));
  a.doc.dispatchEvent(new a.win.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
  assert.equal(panel.hidden, true);
  assert.equal(a.doc.querySelector('[data-ccm-banner]').hidden, false, 'retour à la bannière au premier passage');
  assert.equal(a.doc.activeElement, opener);
  assert.ok(sw2.length);
});

test('turbo:load : la bannière est révélée sur le nouveau body', () => {
  const a = boot({ fragments: mkFrags([]) });
  a.doc.body.innerHTML = '<div data-ccm-banner hidden></div>';
  a.doc.dispatchEvent(new a.win.Event('turbo:load'));
  assert.equal(a.doc.querySelector('[data-ccm-banner]').hidden, false);
});

test('window.timeExpire = 13 mois en ms, indépendant de repromptDays', () => {
  const config = baseConfig();
  config.repromptDays = 30;
  const a = boot({ config, start: false });
  assert.equal(a.win.timeExpire, 34214400000);
});
