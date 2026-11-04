import test from 'node:test';
import assert from 'node:assert/strict';
import { boot, baseConfig, state, gtagCommands, HEAD } from './_helpers.mjs';

test('sans choix : un seul bloc de défauts, aucun update, bannière demandée', () => {
  const { win, doc } = boot({ start: false });
  const cmds = gtagCommands(win);
  assert.equal(cmds.length, 2);
  assert.ok(cmds.every((c) => c.cmd === 'default'));
  assert.deepEqual([...cmds[0].args.region], ['FR', 'DE']);
  assert.equal('region' in cmds[1].args, false);
  assert.equal(cmds[1].args.security_storage, 'granted');
  assert.ok(doc.documentElement.classList.contains('ccm-show'));
  assert.ok(doc.getElementById('ccm-critical').textContent.includes('html.ccm-show [data-ccm-banner]{display:block}'));
  const ready = win.dataLayer.filter((e) => e.event === 'ccm_consent_ready');
  assert.equal(ready.length, 1);
});

test('choix mémorisé : update immédiat, jamais un default après un update', () => {
  const st = state({ p: { analytics: 1, ads: 0, media: 0, support: 0, personalization: 2 }, s: { gtag: 1, googletagmanager: 0, youtube: 0, 'acme-widget': 0, 'tiktok-via-gtm': 0 } });
  const { win, doc } = boot({ cookie: st });
  const cmds = gtagCommands(win);
  const firstUpdate = cmds.findIndex((c) => c.cmd === 'update');
  assert.equal(firstUpdate, 2);
  assert.ok(cmds.slice(firstUpdate).every((c) => c.cmd === 'update'));
  const u = cmds[firstUpdate].args;
  assert.equal(u.analytics_storage, 'granted');
  assert.equal(u.ad_storage, 'denied');
  assert.equal(u.functionality_storage, 'denied', 'finalité partielle = non accordée');
  assert.ok(!doc.documentElement.classList.contains('ccm-show'));
  const idx = win.dataLayer.findIndex((e) => e.event === 'ccm_consent_ready');
  assert.ok(idx > firstUpdate, 'ccm_consent_ready après l update');
});

test('rejeu du head (Turbo) : pas de second bloc de défauts', () => {
  const { win } = boot({ start: false });
  win.eval(HEAD);
  win.eval(HEAD);
  const cmds = gtagCommands(win);
  assert.equal(cmds.filter((c) => c.cmd === 'default').length, 2);
});

test('rejeu du head après un update : aucun default ajouté', () => {
  const st = state({ p: { analytics: 1, ads: 0, media: 0, support: 0, personalization: 0 }, s: { gtag: 1 } });
  const { win } = boot({ cookie: st });
  win.eval(HEAD);
  const cmds = gtagCommands(win);
  assert.equal(cmds.filter((c) => c.cmd === 'default').length, 2);
  assert.equal(cmds.slice(2).filter((c) => c.cmd === 'default').length, 0);
});

test('GCM désactivé : aucune commande consent', () => {
  const config = baseConfig();
  config.gcm.enabled = false;
  const { win } = boot({ config, cookie: state(), start: false });
  assert.equal(gtagCommands(win).length, 0);
});

test('mode audit : cookie ignoré, pas d update', () => {
  const config = baseConfig();
  config.mode = 'audit';
  const st = state({ p: { analytics: 1, ads: 0, media: 0, support: 0, personalization: 0 }, s: { gtag: 1 } });
  const { win, doc } = boot({ config, cookie: st, start: false });
  assert.equal(gtagCommands(win).filter((c) => c.cmd === 'update').length, 0);
  assert.ok(doc.documentElement.classList.contains('ccm-show'));
});

test('après un choix utilisateur : update couvrant tous les signaux + ccm_consent_update', async () => {
  const { win, click, settle } = boot();
  click('[data-ccm-action="accept_all"]');
  await settle();
  const cmds = gtagCommands(win);
  const upd = cmds.filter((c) => c.cmd === 'update');
  assert.equal(upd.length, 1);
  assert.deepEqual(Object.keys(upd[0].args).sort(), ['ad_personalization', 'ad_storage', 'ad_user_data', 'analytics_storage', 'functionality_storage', 'personalization_storage']);
  assert.ok(Object.values(upd[0].args).every((v) => v === 'granted'));
  assert.equal(win.dataLayer.filter((e) => e.event === 'ccm_consent_update').length, 1);
  assert.equal(cmds.slice(cmds.indexOf(upd[0])).filter((c) => c.cmd === 'default').length, 0);
});
