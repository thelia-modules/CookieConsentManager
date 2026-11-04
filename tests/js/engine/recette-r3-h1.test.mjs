import test from 'node:test';
import assert from 'node:assert/strict';
import { boot, baseConfig, state, gtagCommands } from './_helpers.mjs';

const DAY = 86400;
const nowSeconds = () => Math.floor(Date.now() / 1000);
const granted = () => state({
  p: { analytics: 1, ads: 0, media: 1, support: 1, personalization: 0 },
  s: { gtag: 1, googletagmanager: 0, youtube: 1, 'acme-widget': 1, 'tiktok-via-gtm': 0 }
});
const counters = () => {
  const ran = { gtag: 0, youtube: 0 };
  return { ran, fragments: { gtag: { js: () => { ran.gtag++; } }, youtube: { js: () => { ran.youtube++; } } } };
};

// ---- Q3 / uti#C1 : un choix périmé est un choix absent -----------------------

for (const [name, mutate] of [
  ['choix expiré (repromptDays)', (st) => { st.t = nowSeconds() - 183 * DAY; }],
  ['version substantielle', (st, config) => { config.substantialVersion = 12; st.ver = 11; }]
]) {
  test('Q3 ' + name + ' : aucun service non essentiel ne tourne, Consent Mode reste au défaut, bannière demandée', () => {
    const config = baseConfig();
    const st = granted();
    mutate(st, config);
    const { ran, fragments } = counters();
    const { win, doc, cmp } = boot({ config, cookie: st, fragments });
    assert.equal(ran.gtag, 0);
    assert.equal(ran.youtube, 0);
    assert.equal(win.__acme, undefined, 'script balisé non exécuté');
    assert.ok(doc.querySelector('#vid[data-ccm-placeholder]'), 'iframe non restaurée');
    assert.equal(doc.querySelector('iframe'), null);
    assert.equal(gtagCommands(win).filter((c) => c.cmd === 'update').length, 0, 'aucun update : défauts conservés');
    assert.ok(doc.documentElement.classList.contains('ccm-show'));
    assert.equal(cmp.hasConsent('gtag'), false);
    assert.equal(cmp.hasPurposeConsent('analytics'), false);
    const ready = win.dataLayer.find((e) => e.event === 'ccm_consent_ready');
    assert.deepEqual(Object.keys(ready.ccm.services), []);
  });

  test('Q3 ' + name + ' : « Tout accepter » relance les services (action renew)', async () => {
    const config = baseConfig();
    const st = granted();
    mutate(st, config);
    const { ran, fragments } = counters();
    const { win, click, settle, calls } = boot({ config, cookie: st, fragments });
    click('[data-ccm-banner] [data-ccm-action="accept_all"]');
    await settle();
    assert.equal(ran.gtag, 1);
    assert.equal(calls[0].body.action, 'renew');
    assert.ok(gtagCommands(win).some((c) => c.cmd === 'update' && c.args.analytics_storage === 'granted'));
  });

  test('Q3 ' + name + ' : « Tout refuser » ne laisse rien tourner', async () => {
    const config = baseConfig();
    const st = granted();
    mutate(st, config);
    const { ran, fragments } = counters();
    const { win, click, settle } = boot({ config, cookie: st, fragments });
    click('[data-ccm-banner] [data-ccm-action="reject_all"]');
    await settle();
    assert.equal(ran.gtag + ran.youtube, 0);
    assert.equal(win.__acme, undefined);
    const u = gtagCommands(win).filter((c) => c.cmd === 'update').pop().args;
    assert.ok(Object.values(u).every((v) => v === 'denied'));
  });

  test('Q3 ' + name + ' : autoriser une vidéo ne ressuscite pas les anciens accords', async () => {
    const config = baseConfig();
    const st = granted();
    st.s.gtag = 1;
    mutate(st, config);
    const { ran, fragments } = counters();
    const { win, doc, click, settle } = boot({ config, cookie: st, fragments });
    click('#vid [data-ccm-action="service_allow"], #vid button');
    await settle();
    assert.equal(ran.youtube, 1);
    assert.equal(ran.gtag, 0);
    assert.equal(win.__acme, undefined);
    assert.ok(doc.querySelector('iframe'));
  });
}

test('Q3 nouveau service ajouté : les services déjà accordés continuent de tourner', () => {
  const st = granted();
  delete st.s['tiktok-via-gtm'];
  st.p.ads = 1;
  st.s.googletagmanager = 1;
  const config = baseConfig();
  config.services.push({ key: 'newone', purpose: 'analytics', kind: 'script', googleTag: false, user: {}, cookies: [], storage: [] });
  const { ran, fragments } = counters();
  const { doc } = boot({ config, cookie: st, fragments });
  assert.equal(ran.gtag, 1);
  assert.ok(doc.documentElement.classList.contains('ccm-show'), 'la bannière revient pour le nouveau service');
});

test('Q3 head : choix périmé = aucun update Consent Mode et événement ready sans accord', () => {
  const st = granted();
  st.t = nowSeconds() - 200 * DAY;
  const { win } = boot({ cookie: st, start: false });
  assert.equal(gtagCommands(win).filter((c) => c.cmd === 'update').length, 0);
  const ready = win.dataLayer.find((e) => e.event === 'ccm_consent_ready');
  assert.deepEqual(Object.keys(ready.ccm.purposes), []);
  assert.deepEqual(Object.keys(ready.ccm.services), []);
});

// ---- jur#C2 : refus et retrait portent sur tous les signaux gérés ----------------

test('jur#C2 « Tout refuser » : update de tous les signaux gérés, même ceux d aucune finalité', async () => {
  const config = baseConfig();
  config.purposes = config.purposes.filter((p) => p.code !== 'ads');
  config.gcm.defaults[1].ad_storage = 'granted';
  const { win, click, settle } = boot({ config });
  click('[data-ccm-banner] [data-ccm-action="reject_all"]');
  await settle();
  const u = gtagCommands(win).filter((c) => c.cmd === 'update').pop().args;
  for (const signal of ['ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage', 'functionality_storage', 'personalization_storage']) {
    assert.equal(u[signal], 'denied', signal);
  }
  assert.equal('security_storage' in u, false);
});

test('jur#C2 head : un choix mémorisé refuse aussi les signaux des défauts hors finalités', () => {
  const config = baseConfig();
  config.purposes = config.purposes.filter((p) => p.code !== 'ads');
  const { win } = boot({ config, cookie: state(), start: false });
  const u = gtagCommands(win).find((c) => c.cmd === 'update').args;
  assert.equal(u.ad_storage, 'denied');
  assert.equal(u.ad_user_data, 'denied');
});

test('jur#C2 « Tout refuser » depuis la première visite purge les _ga déjà posés', async () => {
  const { doc, click, settle } = boot({ cookies: ['_ga=GA1.1.1; Path=/', '_ga_ABC123=GS1; Path=/', 'autre=1; Path=/'] });
  click('[data-ccm-banner] [data-ccm-action="reject_all"]');
  await settle();
  assert.ok(!/(^|; )_ga=/.test(doc.cookie));
  assert.ok(!/(^|; )_ga_ABC123=/.test(doc.cookie));
  assert.ok(/(^|; )autre=1/.test(doc.cookie), 'les autres cookies restent');
});

test('jur#C2 retrait : les _ga sont purgés', async () => {
  const st = state({ p: { analytics: 1, ads: 0, media: 0, support: 0, personalization: 0 }, s: { gtag: 1 } });
  const { doc, click, settle } = boot({ cookie: st, cookies: ['_ga=GA1.1.1; Path=/'] });
  click('[data-ccm-panel] [data-ccm-service="gtag"]');
  click('[data-ccm-panel] [data-ccm-action="save"]');
  await settle();
  assert.ok(!/(^|; )_ga=/.test(doc.cookie));
});

// ---- uti#C3 : état partiel -------------------------------------------------------

test('uti#C3 une finalité partielle (2) s affiche « mixed »', () => {
  const st = state({ p: { analytics: 1, ads: 2, media: 0, support: 0, personalization: 0 }, s: { gtag: 1, googletagmanager: 1 } });
  const { doc } = boot({ cookie: st });
  assert.equal(doc.querySelector('[data-ccm-purpose="ads"]').getAttribute('aria-checked'), 'mixed');
  assert.equal(doc.querySelector('[data-ccm-purpose="analytics"]').getAttribute('aria-checked'), 'true');
});

test('uti#C3 un seul service sur deux allumé : l interrupteur de la finalité passe à « mixed »', () => {
  const { doc, click } = boot();
  doc.querySelector('[data-ccm-panel]').insertAdjacentHTML('beforeend', '<button role="switch" aria-checked="false" data-ccm-service="tiktok-via-gtm"></button>');
  click('[data-ccm-panel] [data-ccm-service="googletagmanager"]');
  assert.equal(doc.querySelector('[data-ccm-purpose="ads"]').getAttribute('aria-checked'), 'mixed');
  click('[data-ccm-panel] [data-ccm-service="tiktok-via-gtm"]');
  assert.equal(doc.querySelector('[data-ccm-purpose="ads"]').getAttribute('aria-checked'), 'true');
});

// ---- uti#C2 : cookies tiers restants ---------------------------------------------

test('uti#C2 la phrase sur les cookies tiers restants apparaît dès qu un service est refusé', () => {
  const withNote = (cookie) => {
    const ctx = boot({ cookie, start: false });
    ctx.doc.querySelector('[data-ccm-panel]').insertAdjacentHTML('beforeend', '<p data-ccm-withdrawn hidden>note</p>');
    ctx.cmp.__start();
    return ctx.doc.querySelector('[data-ccm-withdrawn]').hidden;
  };
  assert.equal(withNote(state({ p: { analytics: 0 }, s: { gtag: 0 } })), false);
  const all = state({ p: { analytics: 1, ads: 1, media: 1, support: 1, personalization: 1 }, s: { gtag: 1, googletagmanager: 1, youtube: 1, 'acme-widget': 1, 'tiktok-via-gtm': 1 } });
  assert.equal(withNote(all), true);
  assert.equal(withNote(undefined), true, 'sans choix : rien à dire');
});

// ---- jur#C13 : l espace réservé nomme le responsable ------------------------------

const withTemplate = (policy) => {
  const ctx = boot({ start: false });
  const { doc } = ctx;
  doc.body.insertAdjacentHTML('beforeend', '<template data-ccm-placeholder-template><span data-ccm-placeholder-box><span class="who">{{controller}}</span><span data-ccm-if-policy><a class="pol" href="{{policyUrl}}">p</a></span></span></template>' +
    '<script type="application/json" id="ccm-services-meta">' + JSON.stringify({ youtube: { controller: 'Google Ireland Limited', policy_url: policy } }) + '</script>');
  doc.querySelector('#vid').innerHTML = '';
  ctx.cmp.__start();
  return ctx;
};

test('jur#C13 espace réservé construit par le moteur : responsable et lien de politique', () => {
  const { doc } = withTemplate('https://policies.google.com/privacy');
  assert.equal(doc.querySelector('#vid .who').textContent, 'Google Ireland Limited');
  assert.equal(doc.querySelector('#vid .pol').getAttribute('href'), 'https://policies.google.com/privacy');
});

test('jur#C13 sans URL de politique valide, le lien disparaît (schéma javascript: refusé)', () => {
  assert.equal(withTemplate('').doc.querySelector('#vid .pol'), null);
  const bad = withTemplate('javascript:alert(1)').doc;
  assert.equal(bad.querySelector('#vid .pol'), null);
  assert.equal(bad.querySelector('#vid .who').textContent, 'Google Ireland Limited');
});

test('jur#C13 sans entrée de méta : le nom du service tient lieu de responsable', () => {
  const ctx = boot({ start: false });
  ctx.doc.body.insertAdjacentHTML('beforeend', '<template data-ccm-placeholder-template><span data-ccm-placeholder-box><span class="who">{{controller}}</span></span></template>');
  ctx.doc.querySelector('#vid').innerHTML = '';
  ctx.cmp.__start();
  assert.equal(ctx.doc.querySelector('#vid .who').textContent, 'YouTube');
});

// ---- jur#C16 : la modale s ouvre sur le début du message ---------------------------

test('jur#C16 modale : le focus initial ne fait pas défiler le texte, la zone est en haut', () => {
  const config = baseConfig();
  config.options.layout = 'modal';
  const ctx = boot({ config, start: false });
  const { doc } = ctx;
  const banner = doc.querySelector('[data-ccm-banner]');
  banner.insertAdjacentHTML('afterbegin', '<div data-ccm-scroll><a href="/p">policy</a></div>');
  const zone = banner.querySelector('[data-ccm-scroll]');
  zone.scrollTop = 120;
  ctx.cmp.__start();
  assert.equal(doc.activeElement, banner, 'le focus va à la boîte de dialogue, pas au lien en bas du texte');
  assert.equal(zone.scrollTop, 0);
});
