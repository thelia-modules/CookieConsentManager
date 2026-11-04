import test from 'node:test';
import assert from 'node:assert/strict';
import { boot, state, baseConfig } from './_helpers.mjs';

const all = () => state({
  p: { analytics: 1, ads: 1, media: 1, support: 1, personalization: 0 },
  s: { gtag: 1, googletagmanager: 1, youtube: 1, 'acme-widget': 1, 'tiktok-via-gtm': 1 }
});

test('retrait : purge des cookies à motif (_ga_*) sur l hôte et le domaine parent, et du storage', async () => {
  const gtagJs = { js() {}, fallback() {} };
  const a = boot({
    cookie: all(),
    fragments: { gtag: gtagJs, googletagmanager: gtagJs, 'acme-widget': gtagJs },
    cookies: ['_ga_ABC123=1; Path=/; Domain=example.com', '_ga=2; Path=/', '_gid=3; Path=/; Domain=shop.example.com', 'autre=ok; Path=/', 'acme_x=1; Path=/'],
    beforeHead: (win) => { win.localStorage.setItem('acme.session', 'x'); win.localStorage.setItem('keep', 'y'); }
  });
  assert.match(a.doc.cookie, /_ga_ABC123=1/);
  a.click('[data-ccm-action="reject_all"]');
  await a.settle();
  assert.doesNotMatch(a.doc.cookie, /_ga_ABC123|_ga=|_gid|acme_x/);
  assert.match(a.doc.cookie, /autre=ok/);
  assert.equal(a.win.localStorage.getItem('acme.session'), null);
  assert.equal(a.win.localStorage.getItem('keep'), 'y');
  assert.equal(a.calls[0].body.action, 'reject_all');
});

test('retrait d un service script exécuté : rechargement (option) ; sans l option : non', async () => {
  const frag = { gtag: { js() { this.ran = true; }, fallback() {} } };
  const a = boot({ cookie: all(), fragments: frag });
  assert.ok(a.win.tarteaucitron.services.gtag.ran);
  a.click('[data-ccm-action="reject_all"]');
  await a.settle();
  await new Promise((r) => setTimeout(r, 0));
  assert.equal(a.win.__reloaded, true);

  const config = baseConfig();
  config.options.reloadOnWithdraw = false;
  const b = boot({ config, cookie: all(), fragments: frag });
  b.click('[data-ccm-action="reject_all"]');
  await b.settle();
  await new Promise((r) => setTimeout(r, 0));
  assert.equal(b.win.__reloaded, undefined);
});

test('retrait d un media : retour à l espace réservé sans rechargement', async () => {
  const st = state({ p: { analytics: 0, ads: 0, media: 1, support: 0, personalization: 0 }, s: { gtag: 0, googletagmanager: 0, youtube: 1, 'acme-widget': 0, 'tiktok-via-gtm': 0 } });
  let fallbacks = 0;
  const a = boot({ cookie: st, fragments: { youtube: { js() {}, fallback() { fallbacks++; } } } });
  assert.ok(a.doc.querySelector('iframe'));
  a.click('[data-ccm-action="reject_all"]');
  await a.settle();
  assert.equal(a.doc.querySelector('iframe'), null);
  assert.ok(a.doc.querySelector('[data-ccm-placeholder="youtube"]'));
  assert.equal(fallbacks, 1);
  assert.equal(a.win.__reloaded, undefined);
});

test('purge via le panneau : save avec un interrupteur retiré = withdraw', async () => {
  const frag = { gtag: { js() {}, fallback() {} }, googletagmanager: { js() {}, fallback() {} } };
  const a = boot({ cookie: all(), fragments: frag, cookies: ['_ga=2; Path=/'] });
  a.cmp.open();
  assert.equal(a.doc.querySelector('[data-ccm-panel]').hidden, false);
  assert.equal(a.doc.querySelector('[data-ccm-service="gtag"][role="switch"]').getAttribute('aria-checked'), 'true');
  a.click('[role="switch"][data-ccm-service="gtag"]');
  a.click('[data-ccm-action="save"]');
  await a.settle();
  assert.equal(a.calls[0].body.action, 'withdraw');
  assert.equal(a.calls[0].body.services.gtag, 0);
  assert.equal(a.calls[0].body.purposes.analytics, 0);
  assert.doesNotMatch(a.doc.cookie, /_ga=/);
  assert.equal(a.doc.querySelector('[data-ccm-panel]').hidden, true);
});
