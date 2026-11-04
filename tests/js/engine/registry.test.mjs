import test from 'node:test';
import assert from 'node:assert/strict';
import { boot, baseConfig, state, cookieValue, decodeCookie } from './_helpers.mjs';

const getRaw = (doc) => (doc.cookie.match(/(?:^|; )ccm_consent=([^;]*)/) || [])[1];

test('service_allow puis rechargement : pas de bannière, finalité partielle', async () => {
  const a = boot();
  assert.ok(a.doc.querySelector('[data-ccm-placeholder="youtube"] [data-ccm-action="service_allow"]'), 'espace réservé rempli');
  a.click('[data-ccm-action="service_allow"]');
  await a.settle();
  assert.equal(a.calls.length, 1);
  assert.equal(a.calls[0].body.action, 'service_allow');
  assert.equal(a.calls[0].body.source, 'placeholder');
  assert.equal(a.calls[0].body.purposes.media, 2);
  assert.equal(a.calls[0].body.purposes.analytics, 0);
  assert.equal(a.calls[0].body.services.youtube, 1);
  assert.equal(a.calls[0].body.consent_id, null);
  assert.ok(a.doc.querySelector('iframe[src*="youtube-nocookie"]'), 'iframe restaurée');
  assert.equal(a.doc.querySelector('iframe').getAttribute('width'), '560');
  assert.equal(a.cmp.hasConsent('youtube'), true);
  assert.equal(a.cmp.hasPurposeConsent('media'), false);
  const b = boot({ cookie: getRaw(a.doc) });
  assert.ok(!b.doc.documentElement.classList.contains('ccm-show'));
  assert.equal(b.doc.querySelector('[data-ccm-banner]').hidden, true);
  assert.ok(b.doc.querySelector('iframe'), 'iframe restaurée au rechargement');
});

test('règles de relance : finalité 1 + service absent relance ; 0 et 2 non ; choix expiré relance', () => {
  const head = (st) => boot({ cookie: st, start: false }).doc.documentElement.classList.contains('ccm-show');
  const base = { s: { gtag: 1, googletagmanager: 0, youtube: 0, 'acme-widget': 0 } };
  assert.equal(head(state({ ...base, p: { analytics: 1, ads: 0, media: 0, support: 0, personalization: 0 } })), false, 'tiktok-via-gtm absent mais ads=0 : pas de relance');
  assert.equal(head(state({ s: { gtag: 1, googletagmanager: 0, youtube: 0, 'acme-widget': 0 }, p: { analytics: 1, ads: 1, media: 0, support: 0, personalization: 0 } })), true, 'ads=1 et tiktok absent');
  assert.equal(head(state({ s: { gtag: 1, googletagmanager: 0, youtube: 0, 'acme-widget': 0 }, p: { analytics: 1, ads: 0, media: 0, support: 0, personalization: 0 } })), false);
  assert.equal(head(state({ s: { gtag: 1, googletagmanager: 0, youtube: 0, 'acme-widget': 0 }, p: { analytics: 1, ads: 2, media: 0, support: 0, personalization: 0 } })), false, 'partielle ne relance pas');
  assert.equal(head(state({ t: Math.floor(Date.now() / 1000) - 183 * 86400 })), true, 'expiré');
  assert.equal(head(state({ t: Math.floor(Date.now() / 1000) - 181 * 86400 })), false, 'encore valide');
  assert.equal(head('%%%pas-du-base64'), true);
  assert.equal(head(cookieValue({ f: 2 })), true, 'format inconnu = pas de choix');
});

test('le moteur applique la même règle de relance et révèle la bannière', () => {
  const none = boot({ cookie: state({ t: Math.floor(Date.now() / 1000) - 200 * 86400 }) });
  assert.equal(none.doc.querySelector('[data-ccm-banner]').hidden, false);
  const ok = boot({ cookie: state() });
  assert.equal(ok.doc.querySelector('[data-ccm-banner]').hidden, true);
});

test('écriture : repli pd:1 quand le réseau échoue, cookie décodable, UUID v4', async () => {
  const a = boot({ fetchOpts: { fail: true } });
  a.click('[data-ccm-action="accept_all"]');
  await a.settle();
  const st = decodeCookie(getRaw(a.doc));
  assert.equal(st.f, 1);
  assert.equal(st.pd, 1);
  assert.equal(st.ver, 12);
  assert.match(st.id, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
  assert.equal(st.s.youtube, 1);
  assert.equal(st.p.media, 1);
  assert.equal(st.x.matomo, 1);
  assert.equal(a.doc.querySelector('[data-ccm-consent-id]').textContent, st.id);
});

test('repli sur 429 / 5xx ; 4xx autre : pas de renvoi', async () => {
  for (const status of [429, 503]) {
    const a = boot({ fetchOpts: { status } });
    a.click('[data-ccm-action="reject_all"]');
    await a.settle();
    assert.equal(decodeCookie(getRaw(a.doc)).pd, 1, String(status));
  }
  const a = boot({ fetchOpts: { status: 422 } });
  a.click('[data-ccm-action="reject_all"]');
  await a.settle();
  assert.equal(decodeCookie(getRaw(a.doc)).pd, 0);
  const b = boot({ cookie: getRaw(a.doc), fetchOpts: { status: 422 } });
  await b.settle();
  assert.equal(b.calls.length, 0, 'pas de renvoi');
});

test('renvoi au chargement suivant du choix pd:1, UUID conservé', async () => {
  const a = boot({ fetchOpts: { fail: true } });
  a.click('[data-ccm-action="accept_all"]');
  await a.settle();
  const raw = getRaw(a.doc);
  const id = decodeCookie(raw).id;
  const b = boot({ cookie: raw });
  await b.settle();
  assert.equal(b.calls.length, 1);
  assert.equal(b.calls[0].body.consent_id, id);
  assert.equal(b.calls[0].body.action, 'accept_all');
  assert.equal(decodeCookie(getRaw(b.doc)).pd, 0, 'cookie serveur après 200');
  const c = boot({ cookie: getRaw(b.doc) });
  await c.settle();
  assert.equal(c.calls.length, 0);
});

test('le JS n écrase pas un cookie posé par le serveur (pd:0) quand le POST réussit', async () => {
  const a = boot();
  a.click('[data-ccm-action="accept_all"]');
  await a.settle();
  const st = decodeCookie(getRaw(a.doc));
  assert.equal(st.pd, 0);
  assert.equal(st.id, '11111111-2222-4333-8444-555555555555');
  assert.equal(a.cmp.getConsentId(), st.id);
});

for (const mode of ['preview', 'audit']) {
  test(`mode ${mode} : aucun appel réseau`, async () => {
    const config = baseConfig();
    config.mode = mode;
    const a = boot({ config });
    a.click('[data-ccm-action="accept_all"]');
    await a.settle();
    assert.equal(a.calls.length, 0);
    assert.equal(a.cmp.hasConsent('gtag'), true);
    if (mode === 'audit') assert.equal(getRaw(a.doc), undefined, 'pas de cookie de choix');
    else assert.ok(/ccm_preview=/.test(a.doc.cookie));
    assert.ok(!/ccm_consent=/.test(a.doc.cookie));
  });
}

test('payload conforme au contrat §2', async () => {
  const a = boot();
  a.click('[data-ccm-action="accept_all"]');
  await a.settle();
  const b = a.calls[0].body;
  assert.deepEqual(Object.keys(b).sort(), ['action', 'config_version', 'consent_id', 'exempt', 'format', 'locale', 'path', 'purposes', 'served_hash', 'services', 'source']);
  assert.equal(b.path, '/fr/page.html');
  assert.equal(b.served_hash, '9b2e4f0c1a7d');
  assert.equal(b.source, 'banner');
  assert.equal(a.calls[0].url, '/ccm/consent');
});

test('le choix qui suit une relance est tracé renew, même UUID, états réels conservés', async () => {
  const old = Math.floor(Date.now() / 1000) - 183 * 86400;
  const cases = [
    ['accept_all', '[data-ccm-action="accept_all"]', 1],
    ['reject_all', '[data-ccm-action="reject_all"]', 0]
  ];
  for (const [, sel, v] of cases) {
    const a = boot({ cookie: state({ t: old, p: { analytics: 1, ads: 0, media: 0, support: 0, personalization: 0 } }) });
    a.click(sel);
    await a.settle();
    assert.equal(a.calls.length, 1);
    assert.equal(a.calls[0].body.action, 'renew');
    assert.equal(a.calls[0].body.consent_id, '4f1c2b9e-8d7a-4c3e-9b1f-2a6d5e7c8b90');
    assert.equal(a.calls[0].body.purposes.analytics, v);
  }
  const b = boot({ cookie: state({ t: old }) });
  b.click('[data-ccm-action="customize"]');
  b.click('[data-ccm-purpose="analytics"]');
  b.click('[data-ccm-action="save"]');
  await b.settle();
  assert.equal(b.calls[0].body.action, 'renew');
  assert.equal(b.calls[0].body.purposes.analytics, 1);
  const c = boot({ cookie: state({ t: old }) });
  c.click('[data-ccm-placeholder="youtube"] [data-ccm-action="service_allow"]');
  await c.settle();
  assert.equal(c.calls[0].body.action, 'service_allow');
  const d = boot({ cookie: state() });
  d.click('[data-ccm-action="accept_all"]');
  await d.settle();
  assert.equal(d.calls[0].body.action, 'accept_all', 'sans relance, pas de renew');
});
