import test from 'node:test';
import assert from 'node:assert/strict';
import { load } from './_served.mjs';

const scripts = (doc) => Array.from(doc.querySelectorAll('script[src]')).map((s) => s.getAttribute('src'));

test('sans consentement : espace réservé affiché dans le conteneur du fragment youtube, aucun script tiers', () => {
  const t = load();
  assert.match(t.doc.querySelector('.youtube_player').innerHTML, /data-ccm-placeholder-box/);
  assert.match(t.doc.querySelector('.youtube_player').textContent, /Contenu bloqué : YouTube/);
  assert.equal(t.doc.querySelector('.youtube_player iframe'), null);
  assert.equal(t.doc.querySelector('#rewritten iframe'), null);
  assert.deepEqual(scripts(t.doc), []);
  assert.equal(t.calls.length, 0);
  assert.equal(t.doc.documentElement.classList.contains('ccm-show'), true);
});

test('clic Autoriser (espace réservé du fragment) : service_allow une seule fois et la vidéo se charge', async () => {
  const t = load();
  t.click('.youtube_player [data-ccm-action="service_allow"]');
  await t.settle();
  assert.equal(t.calls.length, 1);
  assert.equal(t.calls[0].body.action, 'service_allow');
  assert.equal(t.calls[0].body.source, 'placeholder');
  assert.equal(t.calls[0].body.services.youtube, 1);
  assert.equal(t.calls[0].body.services.gtag, 0);
  const frame = t.doc.querySelector('.youtube_player iframe');
  assert.ok(frame, 'iframe injectée');
  assert.match(frame.getAttribute('src'), /youtube-nocookie\.com\/embed\/dQw4w9WgXcQ/);
  assert.equal(t.doc.activeElement, frame, 'focus sur le contenu débloqué');
  assert.deepEqual(scripts(t.doc), [], 'gtag reste bloqué : seule la vidéo est autorisée');
  t.click('.youtube_player iframe');
  assert.equal(t.calls.length, 1);
});

test('clic Autoriser sur une iframe réécrite : service_allow une seule fois, iframe d origine restaurée avec ses attributs', async () => {
  const t = load();
  t.click('#rewritten [data-ccm-action="service_allow"]');
  await t.settle();
  assert.equal(t.calls.filter((c) => c.body.action === 'service_allow').length, 1);
  const frame = t.doc.querySelector('main > iframe');
  assert.ok(frame);
  assert.equal(frame.getAttribute('src'), 'https://www.youtube-nocookie.com/embed/abc123');
  assert.equal(frame.getAttribute('title'), 'Video produit');
  assert.equal(t.doc.activeElement, frame, 'focus sur le contenu débloqué');
});

test('gtag n est appelé qu après accord', async () => {
  const t = load();
  assert.equal(scripts(t.doc).length, 0);
  assert.equal(t.win.dataLayer.filter((e) => e && e[0] === 'config').length, 0);
  t.click('[data-ccm-action="reject_all"]');
  await t.settle();
  assert.deepEqual(scripts(t.doc), []);
  t.click('[data-ccm-action="accept_all"]');
  await t.settle();
  assert.deepEqual(scripts(t.doc), ['https://www.googletagmanager.com/gtag/js?id=G-TEST12345']);
  const update = t.win.dataLayer.filter((e) => e && e[0] === 'consent' && e[1] === 'update').pop();
  assert.equal(update[2].analytics_storage, 'granted');
});
