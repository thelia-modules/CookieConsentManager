import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { JSDOM } from 'jsdom';

const root = join(dirname(fileURLToPath(import.meta.url)), '../../..');
export const HEAD = readFileSync(join(root, 'assets/js/head.js'), 'utf8');
export const ENGINE = readFileSync(join(root, 'assets/js/engine.js'), 'utf8');

export const baseConfig = () => ({
  format: 1, version: 12, hash: '9b2e4f0c1a7d', locale: 'fr_FR', mode: 'live', endpoint: '/ccm/consent',
  script: '/ccm/12-9b2e4f0c1a7d.js', repromptDays: 182,
  options: { layout: 'bar', closeButton: false, floatingButton: false, reloadOnWithdraw: true, blockEmbeds: true, experimental: false },
  gcm: {
    enabled: true, mode: 'basic',
    defaults: [
      { region: ['FR', 'DE'], ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied', functionality_storage: 'denied', personalization_storage: 'denied', security_storage: 'granted' },
      { region: [], ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied', functionality_storage: 'denied', personalization_storage: 'denied', security_storage: 'granted' }
    ],
    waitForUpdate: 0, adsDataRedaction: false, urlPassthrough: false, uet: false
  },
  purposes: [
    { code: 'analytics', signals: ['analytics_storage'] },
    { code: 'ads', signals: ['ad_storage', 'ad_user_data', 'ad_personalization'] },
    { code: 'media', signals: [] },
    { code: 'personalization', signals: ['functionality_storage', 'personalization_storage'] }
  ],
  services: [
    { key: 'gtag', purpose: 'analytics', kind: 'script', googleTag: true, user: { gtagUa: 'G-ABC123XYZ' }, cookies: ['_ga', '_ga_*', '_gid'], storage: [] },
    { key: 'googletagmanager', purpose: 'ads', kind: 'script', googleTag: true, user: { googletagmanagerId: 'GTM-ABCD12' }, cookies: [], storage: [] },
    { key: 'youtube', purpose: 'media', kind: 'media', googleTag: false, user: {}, cookies: ['VISITOR_INFO1_LIVE', 'YSC', 'PREF'], storage: [], name: 'YouTube' },
    { key: 'acme-widget', purpose: 'support', kind: 'custom', googleTag: false, user: {}, cookies: ['acme_*'], storage: ['acme.session'], name: 'Acme Chat' },
    { key: 'tiktok-via-gtm', purpose: 'ads', kind: 'declarative', googleTag: false, user: {}, cookies: ['_ttp'], storage: [], name: 'TikTok Pixel (via GTM)' }
  ],
  exempt: [{ key: 'matomo', cookies: ['_pk_id.*', '_pk_ses.*'] }]
});

export const cookieValue = (o) => Buffer.from(JSON.stringify(o)).toString('base64url');
export const decodeCookie = (v) => JSON.parse(Buffer.from(v, 'base64url').toString('utf8'));
export const state = (o = {}) => ({
  f: 1, id: '4f1c2b9e-8d7a-4c3e-9b1f-2a6d5e7c8b90', ver: 12, t: Math.floor(Date.now() / 1000),
  p: { analytics: 0, ads: 0, media: 0, support: 0, personalization: 0 }, s: { gtag: 0, googletagmanager: 0, youtube: 0, 'acme-widget': 0, 'tiktok-via-gtm': 0 },
  x: { matomo: 1 }, pd: 0, ...o
});

export const BODY = `
<div data-ccm-banner hidden><button data-ccm-action="accept_all">ok</button><button data-ccm-action="reject_all">no</button><button data-ccm-action="open">prefs</button><button data-ccm-action="customize">custom</button></div>
<div data-ccm-panel hidden>
  <button data-ccm-action="close">x</button>
  <button role="switch" aria-checked="false" data-ccm-purpose="analytics" data-ccm-purpose-label="Mesure d'audience"></button>
  <button role="switch" aria-checked="false" data-ccm-service="gtag"></button>
  <button role="switch" aria-checked="false" data-ccm-purpose="ads"></button>
  <button role="switch" aria-checked="false" data-ccm-service="googletagmanager"></button>
  <button role="switch" aria-checked="true" data-ccm-exempt="matomo"></button>
  <button data-ccm-action="save">save</button><span data-ccm-consent-id></span>
</div>
<div id="vid" data-ccm-placeholder="youtube" data-ccm-src="https://www.youtube-nocookie.com/embed/abc" data-ccm-attr-width="560" data-ccm-attr-title="Vid"></div>
<script type="text/plain" data-ccm-service="acme-widget">window.__acme = (window.__acme||0)+1;</script>`;

export const fetchLog = (win, { status = 200, fail = false } = {}) => {
  const calls = [];
  win.fetch = (url, init) => {
    const body = JSON.parse(init.body);
    calls.push({ url, body });
    if (fail) return Promise.reject(new Error('net'));
    if (status !== 200) return Promise.resolve({ ok: false, status, json: async () => ({}) });
    const id = body.consent_id || '11111111-2222-4333-8444-555555555555';
    const st = { f: 1, id, ver: body.config_version, t: Math.floor(Date.now() / 1000), p: body.purposes, s: body.services, x: body.exempt, pd: 0 };
    win.document.cookie = 'ccm_consent=' + cookieValue(st) + '; Path=/';
    return Promise.resolve({ ok: true, status: 200, json: async () => ({ consent_id: id }) });
  };
  return calls;
};

// Loads head + engine in a jsdom DOM; fragments: { key: { js, fallback } } are set before __start.
export const boot = ({ config = baseConfig(), cookie, cookies = [], url = 'https://shop.example.com/fr/page.html', fragments = {}, fetchOpts, start = true, beforeHead, messages } = {}) => {
  const dom = new JSDOM(`<!doctype html><html><head><script type="application/json" id="ccm-config">${JSON.stringify(config)}</script></head><body>${BODY}${messages ? `<script type="application/json" id="ccm-messages">${JSON.stringify(messages)}</script>` : ''}</body></html>`, { url, runScripts: 'dangerously', pretendToBeVisual: true });
  const win = dom.window;
  if (cookie) win.document.cookie = (config.mode === 'preview' ? 'ccm_preview=' : 'ccm_consent=') + (typeof cookie === 'string' ? cookie : cookieValue(cookie)) + '; Path=/';
  cookies.forEach((c) => { win.document.cookie = c; });
  const calls = fetchLog(win, fetchOpts);
  if (beforeHead) beforeHead(win);
  win.eval(HEAD);
  win.CCMCompat = { create: (adapter) => ({ adapter }) };
  win.eval(ENGINE);
  win.CookieConsentManager.__reload = () => { win.__reloaded = true; };
  win.CookieConsentManager.__boot();
  Object.keys(fragments).forEach((k) => { win.tarteaucitron.services[k] = { key: k, ...fragments[k] }; });
  if (start) win.CookieConsentManager.__start();
  const click = (sel) => win.document.querySelector(sel).dispatchEvent(new win.MouseEvent('click', { bubbles: true, cancelable: true }));
  return { dom, win, calls, click, cmp: win.CookieConsentManager, doc: win.document, settle: () => win.CookieConsentManager.__wait() };
};

export const gtagCommands = (win) => win.dataLayer.filter((e) => e && e[0] === 'consent').map((e) => ({ cmd: e[1], args: e[2] }));
