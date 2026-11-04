import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { boot, baseConfig, state } from './_helpers.mjs';

const css = readFileSync(join(dirname(fileURLToPath(import.meta.url)), '../../../assets/css/ccm.css'), 'utf8');
const media = () => state({ p: { analytics: 0, ads: 0, media: 1, support: 0, personalization: 0 }, s: { gtag: 0, googletagmanager: 0, youtube: 1, 'acme-widget': 0, 'tiktok-via-gtm': 0 } });

test('R2 visiteur#C1 : l iframe restaurée est contrainte à son conteneur et garde son ratio', () => {
  const a = boot({ cookie: media(), fragments: { youtube: { js() {}, fallback() {} } } });
  a.doc.body.insertAdjacentHTML('beforeend', '<div id="v2" data-ccm-placeholder="youtube" data-ccm-src="https://www.youtube-nocookie.com/embed/x" data-ccm-attr-width="560" data-ccm-attr-height="315"></div>');
  a.doc.dispatchEvent(new a.win.Event('turbo:load'));
  const f = a.doc.querySelector('iframe[width="560"][height="315"]') || a.doc.querySelector('iframe[height="315"]');
  assert.ok(f, 'iframe restaurée');
  assert.ok(f.hasAttribute('data-ccm-restored'));
  assert.equal(f.style.getPropertyValue('--ccm-ar'), '560 / 315');
});

test('R2 visiteur#C1 : la feuille de style borne iframes restaurées, espaces réservés et panneau à l écran', () => {
  assert.match(css, /iframe\[data-ccm-restored\]\s*\{[^}]*max-width:\s*100%/);
  assert.match(css, /aspect-ratio:\s*var\(--ccm-ar\)/);
  assert.match(css, /\.ccm-placeholder\s*\{[^}]*max-width:\s*100%/);
  assert.match(css, /\.ccm-panel\s*\{\s*max-width:\s*100vw/);
});

test('R2 visiteur#C2 : après un choix, le focus ramène l élément à l écran (pas de preventScroll)', async () => {
  const a = boot();
  a.doc.body.insertAdjacentHTML('beforeend', '<a href="#ccm-preferences" id="reopen">Cookies</a>');
  const calls = [];
  const orig = a.win.HTMLElement.prototype.focus;
  a.win.HTMLElement.prototype.focus = function (o) { calls.push({ id: this.id, o }); return orig.call(this, o); };
  a.doc.querySelector('[data-ccm-action="accept_all"]').focus();
  calls.length = 0;
  a.click('[data-ccm-action="accept_all"]');
  await a.settle();
  const hit = calls.find((c) => c.id === 'reopen');
  assert.ok(hit, 'le lien de réouverture reçoit le focus');
  assert.ok(!hit.o || !hit.o.preventScroll, 'sans preventScroll');
  assert.equal(a.doc.activeElement.id, 'reopen');
});

const scrolling = (a, over) => {
  const el = a.doc.querySelector('[data-ccm-scroll]');
  Object.defineProperty(el, 'scrollHeight', { configurable: true, get: () => (over ? 300 : 100) });
  Object.defineProperty(el, 'clientHeight', { configurable: true, get: () => 100 });
  return el;
};

test('R2 visiteur#C4 : la zone de texte n est un arrêt de tabulation que si elle défile', () => {
  const a = boot({ start: false });
  a.doc.querySelector('[data-ccm-banner]').insertAdjacentHTML('afterbegin', '<div data-ccm-scroll role="region"></div>');
  const el = scrolling(a, false);
  a.cmp.__start();
  assert.equal(el.hasAttribute('tabindex'), false, 'ne défile pas : pas de tabindex');
  scrolling(a, true);
  a.win.dispatchEvent(new a.win.Event('resize'));
  assert.equal(el.getAttribute('tabindex'), '0', 'défile : tabindex 0');
  scrolling(a, false);
  a.win.dispatchEvent(new a.win.Event('resize'));
  assert.equal(el.hasAttribute('tabindex'), false);
});

test('H4 : le bandeau d aperçu vient des messages servis par le serveur (#ccm-messages), repli anglais sans eux', () => {
  const note = (messages) => { const c = baseConfig(); c.mode = 'preview'; c.locale = 'de_DE'; return boot({ config: c, messages }).doc.querySelector('[data-ccm-preview-note]').textContent; };
  assert.equal(note({ preview: 'Entwurfsvorschau: Simulation, es wird kein Dienst ausgeführt.' }), 'Entwurfsvorschau: Simulation, es wird kein Dienst ausgeführt.');
  assert.match(note(undefined), /^Draft preview/);
  assert.match(note({}), /^Draft preview/);
});

test('H4 : les messages servis couvrent annonce de sauvegarde, contenu débloqué et espace réservé sans gabarit', async () => {
  const a = boot({ messages: { saved: 'Ihre Auswahl wurde gespeichert.', blocked: 'Blockierter Inhalt: {{name}}', allow: '{{name}} zulassen', prefs: 'Einstellungen' } });
  const html = a.cmp.__placeholderHtml ? a.cmp.__placeholderHtml('youtube') : null;
  if (html) { assert.match(html, /Blockierter Inhalt: /); assert.match(html, /zulassen/); assert.match(html, /Einstellungen/); }
  a.click('[data-ccm-banner] [data-ccm-action="accept_all"]');
  await a.settle();
  await new Promise((resolve) => setTimeout(resolve, 60));
  assert.equal(a.doc.querySelector('[role="status"]').textContent, 'Ihre Auswahl wurde gespeichert.');
});

