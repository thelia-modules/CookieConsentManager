// Builds the served script the way ServedScriptAssembler does (order in docs/runtime-contracts.md, "Served script") so that the
// real engine, the real compat layer and real catalogue fragments run together in jsdom.
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { JSDOM } from 'jsdom';

const root = join(dirname(fileURLToPath(import.meta.url)), '../../..');
const read = (p) => readFileSync(join(root, p), 'utf8').trimEnd();

export const servedScript = (keys, custom = {}) => [
  read('assets/js/compat.js'),
  read('assets/js/engine.js'),
  'CookieConsentManager.__boot();',
  ...keys.map((k) => read(`Resources/catalogue/fragments/${k}.js`).trimEnd()),
  ...Object.entries(custom).map(([k, code]) => `tarteaucitron.services[${JSON.stringify(k)}] = {key:${JSON.stringify(k)}, js:function(){\n${code}\n}, fallback:function(){}};`),
  'CookieConsentManager.__start();',
].join('\n') + '\n';

export const HEAD = read('assets/js/head.js');

export const config = () => ({
  format: 1, version: 3, hash: 'fa2b686f8fb6', substantialVersion: 0, locale: 'fr_FR', mode: 'live', endpoint: '/ccm/consent',
  script: '/ccm/3-fa2b686f8fb6.js', repromptDays: 182,
  options: { layout: 'bar', closeButton: false, floatingButton: false, reloadOnWithdraw: true, blockEmbeds: true, experimental: false },
  gcm: {
    enabled: true, mode: 'basic',
    defaults: [{ region: [], ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied', functionality_storage: 'denied', personalization_storage: 'denied', security_storage: 'granted' }],
    waitForUpdate: 0, adsDataRedaction: false, urlPassthrough: false, uet: false,
  },
  purposes: [{ code: 'analytics', signals: ['analytics_storage'] }, { code: 'media', signals: [] }],
  services: [
    { key: 'gtag', purpose: 'analytics', kind: 'script', googleTag: true, user: { gtagUa: 'G-TEST12345' }, cookies: ['_ga', '_ga_*'], storage: [] },
    { key: 'youtube', purpose: 'media', kind: 'media', googleTag: false, user: {}, cookies: ['YSC'], storage: [], name: 'YouTube' },
  ],
  exempt: [],
});

// Markup as the Twig templates of the module render it (data-ccm-* attributes only).
export const PAGE = `
<div data-ccm-banner hidden><button data-ccm-action="reject_all">Refuser</button><button data-ccm-action="accept_all">Accepter</button></div>
<div data-ccm-panel hidden><button data-ccm-action="save">Enregistrer</button></div>
<template data-ccm-placeholder-template><div data-ccm-placeholder-box><p>Contenu bloqué : {{name}}</p><button type="button" data-ccm-action="service_allow" data-ccm-service="{{key}}">Autoriser {{name}}</button></div></template>
<main>
  <div class="youtube_player" videoID="dQw4w9WgXcQ" width="560" height="315"></div>
  <div id="rewritten" data-ccm-placeholder="youtube" data-ccm-src="https://www.youtube-nocookie.com/embed/abc123" data-ccm-attr-title="Video produit">
    <div><button type="button" data-ccm-action="service_allow" data-ccm-service="youtube">Autoriser YouTube</button></div>
  </div>
</main>`;

export const load = ({ keys = ['gtag', 'youtube'], page = PAGE, cookie } = {}) => {
  const dom = new JSDOM(`<!doctype html><html><head><script type="application/json" id="ccm-config">${JSON.stringify(config())}</script></head><body>${page}</body></html>`, {
    url: 'https://shop.example.com/fr/produit.html', runScripts: 'dangerously', pretendToBeVisual: true,
  });
  const win = dom.window;
  const calls = [];
  win.fetch = (url, init) => {
    const body = JSON.parse(init.body);
    calls.push({ url, body });
    win.document.cookie = 'ccm_consent=' + Buffer.from(JSON.stringify({ f: 1, id: '11111111-2222-4333-8444-555555555555', ver: 3, t: Math.floor(Date.now() / 1000), p: body.purposes, s: body.services, x: body.exempt, pd: 0 })).toString('base64url') + '; Path=/';
    return Promise.resolve({ ok: true, status: 200, json: async () => ({}) });
  };
  if (cookie) win.document.cookie = 'ccm_consent=' + Buffer.from(JSON.stringify(cookie)).toString('base64url') + '; Path=/';
  win.eval(HEAD);
  win.eval(servedScript(keys));
  const click = (sel) => win.document.querySelector(sel).dispatchEvent(new win.MouseEvent('click', { bubbles: true, cancelable: true }));
  return { win, doc: win.document, calls, click, settle: () => win.CookieConsentManager.__wait() };
};
