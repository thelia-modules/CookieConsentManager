import test from 'node:test';
import assert from 'node:assert/strict';
import { boot, baseConfig, state } from './_helpers.mjs';

const matomo = (log) => ({ matomo: { js() { log.push('matomo:js'); }, fallback() {} } });
const choice = (x) => state({ x });

test('close : au premier passage revient à la bannière, sans appel registre', async () => {
  const a = boot();
  const banner = a.doc.querySelector('[data-ccm-banner]');
  a.click('[data-ccm-action="customize"]');
  assert.equal(a.doc.querySelector('[data-ccm-panel]').hidden, false);
  assert.equal(banner.hidden, true);
  a.click('[data-ccm-action="close"]');
  assert.equal(a.doc.querySelector('[data-ccm-panel]').hidden, true);
  assert.equal(banner.hidden, false);
  await a.settle();
  assert.equal(a.calls.length, 0);
});

test('close : en réouverture, ferme sans rien changer', async () => {
  const a = boot({ cookie: state() });
  a.cmp.open();
  assert.equal(a.doc.querySelector('[data-ccm-panel]').hidden, false);
  a.click('[data-ccm-action="close"]');
  assert.equal(a.doc.querySelector('[data-ccm-panel]').hidden, true);
  assert.equal(a.doc.querySelector('[data-ccm-banner]').hidden, true);
  await a.settle();
  assert.equal(a.calls.length, 0);
});

test('service exempté : exécuté sans consentement tant que pas d opposition', () => {
  const log = [];
  boot({ fragments: matomo(log) });
  assert.deepEqual(log, ['matomo:js']);
  const log2 = [];
  boot({ cookie: choice({ matomo: 1 }), fragments: matomo(log2) });
  assert.deepEqual(log2, ['matomo:js']);
});

test('service exempté : opposition mémorisée = pas d exécution', () => {
  const log = [];
  boot({ cookie: choice({ matomo: 0 }), fragments: matomo(log) });
  assert.deepEqual(log, []);
});

test('Tout refuser = opposition : purge, rechargement ; retrait de l opposition = ré-exécution', async () => {
  const log = [];
  const a = boot({ fragments: matomo(log), cookies: ['_pk_id.1.abcd=1; Path=/', '_pk_ses.1.abcd=1; Path=/', 'autre=ok; Path=/'] });
  a.click('[data-ccm-action="reject_all"]');
  await a.settle();
  await new Promise((r) => setTimeout(r, 0));
  assert.equal(a.calls[0].body.exempt.matomo, 0);
  assert.doesNotMatch(a.doc.cookie, /_pk_/);
  assert.match(a.doc.cookie, /autre=ok/);
  assert.equal(a.win.__reloaded, true);
  await a.cmp.setConsent({ exempt: { matomo: 1 } });
  assert.deepEqual(log, ['matomo:js', 'matomo:js']);
});

test('{{purposeLabel}} : libellé du DOM, repli sur le code', () => {
  const a = boot();
  a.doc.body.insertAdjacentHTML('beforeend', '<template data-ccm-placeholder-template><p>{{purposeLabel}}|{{purpose}}|{{name}}</p></template>');
  const html = a.win.tarteaucitron.adapter.placeholderHtml;
  assert.equal(html('gtag'), "<p>Mesure d&#39;audience|analytics|gtag</p>");
  assert.equal(html('youtube'), '<p>media|media|YouTube</p>');
});

test('service exempté : ses paramètres publiés sont lus par le fragment (tarteaucitron.user)', () => {
  const config = baseConfig();
  config.exempt = [{ key: 'matomo', user: { matomoId: '3', matomoHost: 'https://s.test' }, cookies: ['_pk_id.*'] }];
  let seen = null;
  const a = boot({ config, fragments: { matomo: { js() { seen = Object.assign({}, a.win.tarteaucitron.user); }, fallback() {} } }, start: false });
  a.win.CookieConsentManager.__start();
  assert.equal(seen && seen.matomoId, '3');
  assert.equal(seen && seen.matomoHost, 'https://s.test');
});

test('service strictement nécessaire (finalité essential) : exécuté sans consentement, même après Tout refuser', async () => {
  const config = baseConfig();
  config.services.push({ key: 'payzen', purpose: 'essential', kind: 'custom', googleTag: false, user: {}, cookies: [], storage: [], name: 'Payzen' });
  const log = [];
  const a = boot({ config, fragments: { payzen: { js() { log.push('payzen:js'); }, fallback() {} } } });
  assert.deepEqual(log, ['payzen:js']);
  a.click('[data-ccm-action="reject_all"]');
  await a.settle();
  assert.deepEqual(log, ['payzen:js'], 'exécuté une seule fois');
});
