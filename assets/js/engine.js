(function (w, d) {
  'use strict';
  if (w.CookieConsentManager) return;

  // English only: the texts of the visitor's language come from the server (#ccm-messages), through the translator of Thelia.
  const FALLBACK_TEXTS = { saved: 'Your choices have been saved.', allowed: 'Content unlocked.', allow: 'Allow {{name}}', prefs: 'Preferences', blocked: 'Blocked content: {{name}}', preview: 'Draft preview: simulation, no service is run.', compat_allow: 'Allow', compat_fallback: 'is disabled.' };
  const ADS_SIGNALS = ['analytics_storage', 'ad_storage', 'ad_user_data', 'ad_personalization'];
  const FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[role="switch"],[tabindex]:not([tabindex="-1"])';

  let cfg = null, adapter = null, cur = null, raw = null, mode = 'live', cname = 'ccm_consent';
  let ready = false, started = false, bound = false, panelOpen = false, activeModal = null, opener = null;
  let inflight = Promise.resolve(), live = null;
  const ran = {}, pre = [], cbs = {}, changeCbs = [], restored = [], scripts = [];
  let mem = null;

  const $ = (s, r) => (r || d).querySelector(s);
  const $$ = (s, r) => Array.from((r || d).querySelectorAll(s));
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => '&#' + c.charCodeAt(0) + ';');
  const log = (m) => { try { if (w.ccmDebug) console.warn('[ccm] ' + m); } catch (e) { /* noop */ } };
  const now = () => Math.floor(Date.now() / 1000);
  let messages = null;
  const text = (k) => {
    if (!messages) {
      const el = d.getElementById('ccm-messages');
      try { messages = JSON.parse(el.textContent) || {}; } catch (e) { messages = {}; }
    }
    return messages[k] || FALLBACK_TEXTS[k];
  };
  const toRe = (p) => new RegExp('^' + p.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*') + '$');
  const svc = (k) => (cfg.services.find((s) => s.key === k) || null);
  const codes = () => {
    const out = [];
    cfg.purposes.forEach((p) => out.indexOf(p.code) < 0 && out.push(p.code));
    cfg.services.forEach((s) => s.purpose !== 'essential' && out.indexOf(s.purpose) < 0 && out.push(s.purpose));
    return out;
  };
  const servicesOf = (c) => cfg.services.filter((s) => s.purpose === c);

  // ---- cookie -------------------------------------------------------------
  const getCookie = (n) => {
    const m = d.cookie.match(new RegExp('(?:^|; )' + n + '=([^;]*)'));
    return m ? m[1] : null;
  };
  const decode = (v) => {
    try {
      let b = v.replace(/-/g, '+').replace(/_/g, '/');
      while (b.length % 4) b += '=';
      const j = JSON.parse(decodeURIComponent(Array.from(atob(b), (c) => '%' + ('0' + c.charCodeAt(0).toString(16)).slice(-2)).join('')));
      if (!j || j.f !== 1) return null;
      j.p = j.p || {}; j.s = j.s || {}; j.x = j.x || {};
      return j;
    } catch (e) { return null; }
  };
  const encode = (o) => btoa(encodeURIComponent(JSON.stringify(o)).replace(/%([0-9A-F]{2})/g, (m, h) => String.fromCharCode(parseInt(h, 16)))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  const writeCookie = (st) => {
    if (mode === 'audit') return;
    const v = encode(st);
    d.cookie = cname + '=' + v + '; Path=/; Max-Age=34214400; SameSite=Lax' + (w.location.protocol === 'https:' ? '; Secure' : '');
    raw = getCookie(cname);
  };
  const readState = () => {
    if (mode === 'audit') return mem;
    const v = getCookie(cname);
    raw = v;
    return v ? decode(v) : null;
  };
  const uuid = () => {
    const b = new Uint8Array(16);
    w.crypto.getRandomValues(b);
    b[6] = (b[6] & 15) | 64; b[8] = (b[8] & 63) | 128;
    const h = Array.from(b, (x) => (x + 256).toString(16).slice(1));
    return h.slice(0, 4).join('') + '-' + h.slice(4, 6).join('') + '-' + h.slice(6, 8).join('') + '-' + h.slice(8, 10).join('') + '-' + h.slice(10).join('');
  };

  // ---- resolution ---------------------------------------------------------
  const exemptOn = (st, k) => !st || st.x[k] !== 0;
  const granted = (st, k) => !!st && st.s[k] === 1;
  const gcmOpen = (st) => !!st && !stale(st) && cfg.purposes.some((p) => (p.signals || []).some((g) => ADS_SIGNALS.indexOf(g) >= 0) && st.p[p.code] >= 1);
  const gcmFree = (k) => {
    const s = svc(k);
    return !(s && s.googleTag && cfg.gcm.enabled && cfg.gcm.mode !== 'advanced');
  };
  const necessary = (k) => { const s = svc(k); return !!s && s.purpose === 'essential'; };
  // An expired choice, one without a whole-number time, one dated more than a day ahead or one older than the last substantial version is no consent at all: nothing optional runs until the visitor answers again.
  const stale = (st) => !!st && (!Number.isInteger(st.t) || st.t + cfg.repromptDays * 86400 <= now() || st.t > now() + 86400 || (cfg.substantialVersion > 0 && (st.ver || 0) < cfg.substantialVersion));
  const fresh = (st) => (st && !stale(st) ? st : null);
  const on = (st, k) => necessary(k) || (!stale(st) && granted(st, k) && (gcmFree(k) || gcmOpen(st)));
  const needsBanner = (st) => {
    if (!st || stale(st)) return true;
    return cfg.services.some((s) => st.s[s.key] === undefined && st.p[s.purpose] === 1);
  };
  const snapshot = (st) => ({
    purposes: Object.assign({}, st ? st.p : {}),
    services: Object.assign({}, st ? st.s : {}),
    version: cfg.version
  });

  // ---- Consent Mode and dataLayer -----------------------------------------
  const gtag = function () { w.dataLayer.push(arguments); };
  // Every signal the module manages: those of the defaults sent by the head script and those carried by a purpose (security_storage stays granted, never updated).
  const gcmSignals = () => {
    const out = [];
    const add = (g) => { if (g !== 'region' && g !== 'wait_for_update' && g !== 'security_storage' && out.indexOf(g) < 0) out.push(g); };
    (cfg.gcm.defaults || []).forEach((o) => Object.keys(o).forEach(add));
    cfg.purposes.forEach((p) => (p.signals || []).forEach(add));
    return out;
  };
  const updateGcm = (st) => {
    if (!cfg.gcm.enabled) return;
    const u = {}, ok = fresh(st);
    gcmSignals().forEach((g) => { u[g] = 'denied'; });
    cfg.purposes.forEach((p) => (p.signals || []).forEach((g) => {
      if (ok && ok.p[p.code] === 1) u[g] = 'granted';
    }));
    (w.gtag || gtag)('consent', 'update', u);
    if (cfg.gcm.uet && u.ad_storage) (w.uetq = w.uetq || []).push('consent', 'update', { ad_storage: u.ad_storage });
  };
  const pushEvent = (event) => {
    w.dataLayer = w.dataLayer || [];
    w.dataLayer.push({ event, ccm: snapshot(cur) });
  };

  // ---- purge --------------------------------------------------------------
  const expire = (n) => {
    const h = w.location.hostname.split('.'), doms = [''];
    for (let i = 0; i < h.length - 1; i++) doms.push(h.slice(i).join('.'));
    const dir = w.location.pathname.replace(/[^/]*$/, '') || '/';
    [...new Set(['/', dir])].forEach((p) => doms.forEach((dm) => {
      d.cookie = n + '=; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=' + p + (dm ? '; Domain=' + dm : '');
    }));
  };
  const purge = (s) => {
    (s.cookies || []).forEach((pat) => {
      const re = toRe(pat);
      d.cookie.split('; ').forEach((c) => { const n = c.split('=')[0]; if (n && re.test(n)) expire(n); });
    });
    (s.storage || []).forEach((pat) => {
      const re = toRe(pat);
      ['localStorage', 'sessionStorage'].forEach((t) => {
        try {
          const st = w[t], ks = [];
          for (let i = 0; i < st.length; i++) ks.push(st.key(i));
          ks.forEach((k) => re.test(k) && st.removeItem(k));
        } catch (e) { /* storage indisponible */ }
      });
    });
  };

  // ---- services -----------------------------------------------------------
  const runService = (s) => {
    if (ran[s.key] || s.kind === 'declarative' || mode === 'preview') return;
    const impl = w.tarteaucitron && w.tarteaucitron.services && w.tarteaucitron.services[s.key];
    if (!impl || typeof impl.js !== 'function') { log('service sans js : ' + s.key); return; }
    ran[s.key] = 1;
    try { impl.js(); } catch (e) { log('js() ' + s.key + ' : ' + e.message); }
  };
  const runFallback = (s) => {
    const impl = w.tarteaucitron && w.tarteaucitron.services && w.tarteaucitron.services[s.key];
    if (impl && typeof impl.fallback === 'function') {
      try { impl.fallback(); } catch (e) { log('fallback() ' + s.key + ' : ' + e.message); }
    }
  };
  const fire = (name, detail) => {
    try { d.dispatchEvent(new w.CustomEvent(name, { detail })); } catch (e) { /* noop */ }
  };
  const announce = (k) => {
    fire('ccm:service:' + k + ':allowed', { service: k });
    (cbs[k] || []).slice().forEach((cb) => { try { cb(); } catch (e) { log('onConsent ' + k + ' : ' + e.message); } });
  };

  // ---- DOM: placeholders, tagged scripts ----------------------------------
  // Controller and policy of the services, written next to the placeholder template by the server (the published configuration does not carry them).
  let meta = null;
  const metaOf = (key) => {
    if (!meta) {
      const el = d.getElementById('ccm-services-meta');
      try { meta = JSON.parse(el.textContent) || {}; } catch (e) { meta = {}; }
    }
    return meta[key] || {};
  };
  const placeholderHtml = (key) => {
    const s = svc(key) || {}, t = $('template[data-ccm-placeholder-template]'), m = metaOf(key);
    const h = t ? t.innerHTML : '<span data-ccm-placeholder-box><span>' + text('blocked') + '</span> ' +
      '<button type="button" data-ccm-action="service_allow" data-ccm-service="{{key}}">' + text('allow') + '</button> ' +
      '<button type="button" data-ccm-action="open">' + text('prefs') + '</button></span>';
    // The purpose labels travel with the metadata (the panel that carries them is not in the page until it is opened).
    const lab = $('[data-ccm-purpose="' + (s.purpose || '') + '"][data-ccm-purpose-label]');
    const label = metaOf('@purposes')[s.purpose] || (lab && lab.getAttribute('data-ccm-purpose-label')) || s.purpose || '';
    const policy = /^(https?:\/\/|\/(?!\/))/i.test(m.policy_url || '') && (m.policy_url || '').indexOf('\\') < 0 ? m.policy_url : '';
    const name = s.name || key;
    const out = h.replace(/\{\{\s*(key|name|purposeLabel|purpose|controller|policyUrl)\s*\}\}/g, (x, k) => esc(
      k === 'key' ? key : k === 'name' ? name : k === 'purposeLabel' ? label : k === 'controller' ? (m.controller || name) : k === 'policyUrl' ? policy : (s.purpose || '')
    ));
    if (policy || out.indexOf('data-ccm-if-policy') < 0) return out;
    const box = d.createElement('div');
    box.innerHTML = out;
    $$('[data-ccm-if-policy]', box).forEach((e) => e.parentNode.removeChild(e));
    return box.innerHTML;
  };
  // Attributes a placeholder may hand back to the element it stands for: never an event handler, srcdoc or an address.
  const attrAllowed = (n) => /^(class|id|width|height|title|allow|allowfullscreen|frameborder|loading|referrerpolicy|name|style|scrolling|sandbox|videoid|aria-[a-z-]+|data-(?!ccm-)[a-z0-9_-]+)$/.test(n);
  // https, or protocol-relative as the server accepts it (EmbedRules); never a script scheme nor `/\` read as `//`.
  const safeSrc = (u) => /^(https:)?\/\/[^/\\]/i.test(u || '');
  const restoreIframe = (ph) => {
    if (!safeSrc(ph.getAttribute('data-ccm-src'))) return null;
    const f = d.createElement('iframe');
    Array.from(ph.attributes).forEach((a) => {
      if (a.name.indexOf('data-ccm-attr-') === 0 && attrAllowed(a.name.slice(14))) f.setAttribute(a.name.slice(14), a.value);
    });
    if (!f.getAttribute('title')) f.setAttribute('title', (svc(ph.getAttribute('data-ccm-placeholder')) || {}).name || ph.getAttribute('data-ccm-placeholder'));
    f.setAttribute('data-ccm-restored', '');
    const iw = parseFloat(f.getAttribute('width')), ih = parseFloat(f.getAttribute('height'));
    if (iw > 0 && ih > 0) f.style.setProperty('--ccm-ar', iw + ' / ' + ih);
    f.setAttribute('src', ph.getAttribute('data-ccm-src'));
    ph.parentNode.replaceChild(f, ph);
    restored.push({ key: ph.getAttribute('data-ccm-placeholder'), frame: f, ph });
    return f;
  };
  // Placeholder without src: the block is emptied, the theme attributes move to the element and the upstream fragment renders it.
  const releasePlaceholder = (ph, k) => {
    Array.from(ph.attributes).forEach((a) => {
      if (a.name.indexOf('data-ccm-attr-') !== 0) return;
      const n = a.name.slice(14);
      if (!attrAllowed(n)) return;
      if (n === 'class') a.value.split(/\s+/).forEach((c) => c && ph.classList.add(c)); else ph.setAttribute(n, a.value);
    });
    ph.setAttribute('data-ccm-released', '');
    ph.innerHTML = '';
    const s = svc(k);
    if (s && s.kind === 'media') { ran[k] = 0; runService(s); }
  };
  const syncPlaceholders = (root) => {
    $$('[data-ccm-placeholder]', root).forEach((ph) => {
      const k = ph.getAttribute('data-ccm-placeholder');
      if (on(cur, k) && mode !== 'preview') {
        if (safeSrc(ph.getAttribute('data-ccm-src'))) restoreIframe(ph);
        else if (!ph.hasAttribute('data-ccm-released')) releasePlaceholder(ph, k);
      } else if (!ph.firstElementChild && !ph.textContent.trim()) {
        ph.removeAttribute('data-ccm-released');
        ph.innerHTML = placeholderHtml(k);
      }
    });
    restored.slice().forEach((r) => {
      if (!on(cur, r.key) && r.frame.parentNode) {
        r.frame.removeAttribute('src');
        r.frame.parentNode.replaceChild(r.ph, r.frame);
        restored.splice(restored.indexOf(r), 1);
      }
    });
  };
  const scriptAllowed = (kind, key) => (kind === 'service' ? on(cur, key) : !!fresh(cur) && cur.p[key] === 1);
  const syncScripts = (root) => {
    $$('script[type="text/plain"][data-ccm-service],script[type="text/plain"][data-ccm-purpose]', root).forEach((old) => {
      const kind = old.hasAttribute('data-ccm-service') ? 'service' : 'purpose';
      const key = old.getAttribute('data-ccm-' + kind);
      if (!scriptAllowed(kind, key) || mode === 'preview') return;
      const s = d.createElement('script');
      Array.from(old.attributes).forEach((a) => {
        if (a.name !== 'type' && a.name.indexOf('data-ccm-') !== 0) s.setAttribute(a.name, a.value);
      });
      // Under a CSP the browser hides the nonce attribute (it reads empty): only the property carries it.
      if (old.nonce) s.nonce = old.nonce;
      s.setAttribute('data-ccm-active', key);
      if (old.src) s.async = false; else s.text = old.text;
      scripts.push({ kind, key });
      old.parentNode.replaceChild(s, old);
    });
  };

  // ---- interface ----------------------------------------------------------
  const visible = (el) => !el.closest('[hidden]');
  const focusables = (root) => $$(FOCUSABLE, root).filter(visible);
  const sayLive = (kind) => {
    if (!live || !live.isConnected) {
      live = $('[data-ccm-live]');
      if (!live) {
        live = d.createElement('div');
        live.setAttribute('role', 'status');
        live.setAttribute('aria-live', 'polite');
        live.style.cssText = 'position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)';
        d.body.appendChild(live);
      }
    }
    const msg = live.getAttribute('data-ccm-msg-' + kind) || text(kind);
    live.textContent = '';
    setTimeout(() => { live.textContent = msg; }, 30);
  };
  // v: true, false or 'mixed' (a purpose of which only some services are granted, state 2 of the `ccm_consent` cookie).
  const setSwitch = (el, v) => {
    if (el.tagName === 'INPUT') { el.checked = v === true; el.indeterminate = v === 'mixed'; }
    el.setAttribute('aria-checked', v === 'mixed' ? 'mixed' : v ? 'true' : 'false');
  };
  const isOn = (el) => (el.tagName === 'INPUT' ? el.checked : el.getAttribute('aria-checked') === 'true');
  const syncSwitches = () => {
    const ok = fresh(cur);
    $$('[role="switch"][data-ccm-purpose]').forEach((el) => {
      const v = ok ? ok.p[el.getAttribute('data-ccm-purpose')] : 0;
      setSwitch(el, v === 1 ? true : v === 2 ? 'mixed' : false);
    });
    $$('[role="switch"][data-ccm-service]').forEach((el) => setSwitch(el, granted(ok, el.getAttribute('data-ccm-service'))));
    // Cookies already set by third parties stay in the browser after a refusal or a withdrawal: the panel says so as soon as something is refused.
    const refused = !!ok && cfg.services.some((o) => !necessary(o.key) && ok.s[o.key] !== 1);
    $$('[data-ccm-withdrawn]').forEach((el) => { el.hidden = !refused; });
    $$('[role="switch"][data-ccm-exempt]').forEach((el) => setSwitch(el, !cur || cur.x[el.getAttribute('data-ccm-exempt')] !== 0));
  };
  const syncConsentId = () => {
    $$('[data-ccm-consent-id]').forEach((el) => {
      const v = cur && cur.id ? cur.id : '';
      if ('value' in el && el.tagName === 'INPUT') el.value = v; else el.textContent = v;
    });
  };
  const sheet = () => {
    try { return !!(w.matchMedia && w.matchMedia('(max-width: 767px)').matches); } catch (e) { return false; }
  };
  const trap = (el) => {
    activeModal = el;
    if (el && !el.contains(d.activeElement)) {
      // The banner takes the focus itself: a link at the end of its text would scroll the message and hide its first sentence.
      const f = el.hasAttribute('data-ccm-banner') ? null : focusables(el)[0];
      if (f) f.focus(); else { el.setAttribute('tabindex', '-1'); el.focus({ preventScroll: true }); }
    }
    if (el) $$('[data-ccm-scroll]', el).forEach((t) => { t.scrollTop = 0; });
  };
  // The text of the first level is a tab stop only while it scrolls (WCAG 2.1.1 without a useless stop).
  const syncScrollable = () => {
    $$('[data-ccm-scroll]').forEach((el) => {
      if (el.scrollHeight > el.clientHeight + 1) el.setAttribute('tabindex', '0'); else el.removeAttribute('tabindex');
    });
  };
  const syncBanner = () => {
    const want = needsBanner(cur) && !panelOpen;
    // Small screens: the modal becomes a bottom sheet that leaves the page usable (no overlay, no focus trap).
    const modal = cfg.options.layout === 'modal' && !sheet();
    let height = 0;
    $$('[data-ccm-banner]').forEach((b) => {
      if (!b.hasAttribute('role')) b.setAttribute('role', 'dialog');
      b.setAttribute('aria-modal', modal ? 'true' : 'false');
      const was = b.hidden;
      b.hidden = !want;
      if (want && modal) {
        if (was) trap(b); else if (!activeModal || !activeModal.isConnected) activeModal = b;
      } else if (activeModal === b) activeModal = null;
      if (want && !modal) height = Math.max(height, b.offsetHeight || 0);
    });
    const root = d.documentElement;
    root.classList[want ? 'add' : 'remove']('ccm-show');
    root.classList[want && !modal ? 'add' : 'remove']('ccm-reserve');
    if (want && !modal) root.style.setProperty('--ccm-reserve', height + 'px'); else root.style.removeProperty('--ccm-reserve');
    if (!want && !panelOpen && activeModal) activeModal = null;
    syncScrollable();
  };
  const previewNote = () => {
    if (mode !== 'preview' || $('[data-ccm-preview-note]')) return;
    const n = d.createElement('div');
    n.className = 'ccm ccm-preview-note';
    n.setAttribute('data-ccm-preview-note', '');
    n.setAttribute('role', 'note');
    n.textContent = text('preview');
    d.body.appendChild(n);
  };
  const syncUi = (root) => {
    syncBanner();
    syncSwitches();
    syncConsentId();
    syncPlaceholders(root);
    syncScripts(root);
  };
  // The second level is not in the page: it is fetched from its published address the first time the visitor opens it, then it stays in the page.
  // The draft preview and the audit carry it inline.
  let panelLoad = null;
  const loadPanel = () => {
    if ($('[data-ccm-panel]')) return Promise.resolve(true);
    if (panelLoad) return panelLoad;
    if (!cfg.panel || typeof w.fetch !== 'function') return Promise.resolve(false);
    panelLoad = w.fetch(cfg.panel, { credentials: 'omit' })
      .then((r) => (r.ok ? r.text() : Promise.reject(new Error('status ' + r.status))))
      .then((html) => {
        const box = d.createElement('div');
        box.innerHTML = html;
        Array.from(box.childNodes).forEach((n) => d.body.appendChild(n));
        return !!$('[data-ccm-panel]');
      })
      .catch((e) => { log('panel: ' + e.message); return false; })
      .then((ok) => { panelLoad = null; return ok; });
    return panelLoad;
  };
  // Without the panel (network, blocked request) the page of the preferences is the way out: it lists the services and holds the same button.
  const panelUnavailable = () => {
    const url = cfg.preferencesUrl;
    if (!url || mode !== 'live') return;
    try {
      if (new URL(url, w.location.href).pathname !== w.location.pathname) w.location.assign(url);
    } catch (e) { /* noop */ }
  };
  const openPanel = (from) => {
    const p = $('[data-ccm-panel]');
    if (!p) {
      const asker = from || d.activeElement;
      loadPanel().then((ok) => (ok ? openPanel(asker) : panelUnavailable()));
      return;
    }
    opener = from || d.activeElement;
    panelOpen = true;
    if (from && from.hasAttribute && from.hasAttribute('data-ccm-open-exempt')) $$('[data-ccm-exempt-group]').forEach((g) => { g.open = true; });
    syncSwitches();
    syncConsentId();
    p.hidden = false;
    p.setAttribute('role', 'dialog');
    p.setAttribute('aria-modal', 'true');
    syncBanner();
    trap(p);
  };
  const closePanel = (restore) => {
    const p = $('[data-ccm-panel]');
    if (!panelOpen) return;
    panelOpen = false;
    activeModal = null;
    if (p) p.hidden = true;
    syncBanner();
    if (restore !== false && opener && opener.isConnected && typeof opener.focus === 'function') opener.focus();
    opener = null;
  };

  // ---- applying a state change --------------------------------------------
  const apply = (prev, next, user) => {
    cur = next;
    let reload = false;
    const adv = cfg.gcm.enabled && cfg.gcm.mode === 'advanced';
    cfg.services.forEach((s) => {
      if (necessary(s.key)) { runService(s); return; }
      const was = on(prev, s.key), is = on(next, s.key);
      if (is && !was) { runService(s); announce(s.key); }
      if (user && !is && !was) purge(s);
      if (was && !is) {
        purge(s);
        if (s.kind === 'media') { ran[s.key] = 0; runFallback(s); }
        else if (cfg.options.reloadOnWithdraw && ran[s.key] && !(adv && s.googleTag)) reload = true;
      }
      if (!is && !was && !prev && s.kind === 'media') runFallback(s);
    });
    cfg.exempt.forEach((e) => {
      if (exemptOn(prev, e.key) && !exemptOn(next, e.key)) {
        purge(e);
        if (ran[e.key] && cfg.options.reloadOnWithdraw) reload = true;
        ran[e.key] = 0;
      }
      if (exemptOn(next, e.key) && !ran[e.key]) { runService({ key: e.key, kind: 'script' }); announce(e.key); }
    });
    if (scripts.some((s) => !scriptAllowed(s.kind, s.key)) && cfg.options.reloadOnWithdraw) reload = true;
    syncUi();
    if (prev || user) updateGcm(next);
    return reload;
  };

  // A choice that could not be recorded is resent with an increasing delay (1 min, doubling each time) and dropped after 8 failed attempts.
  // The state lives in localStorage, next to the pending choice of the cookie; a request in flight also holds a short grace period, so that a page opened right after a click does not send the same choice twice.
  const RETRY = { grace: 30, base: 60, max: 8 };
  const retryKey = () => cname + '_retry';
  const readRetry = () => {
    try {
      const j = JSON.parse(w.localStorage.getItem(retryKey()));
      return j && typeof j.n === 'number' && typeof j.next === 'number' ? j : null;
    } catch (e) { return null; }
  };
  const writeRetry = (n, next) => { try { w.localStorage.setItem(retryKey(), JSON.stringify({ n, next })); } catch (e) { /* noop */ } };
  const clearRetry = () => { try { w.localStorage.removeItem(retryKey()); } catch (e) { /* noop */ } };

  const send = (payload, resend) => {
    if (mode !== 'live') return Promise.resolve();
    const failed = resend ? (readRetry() || { n: 0 }).n : 0;
    const fallback = (pd) => {
      if (!cur) return;
      if (pd && failed + 1 >= RETRY.max) { log('registry: giving up after ' + RETRY.max + ' attempts'); pd = 0; }
      if (pd) writeRetry(failed + 1, now() + RETRY.base * Math.pow(2, failed));
      else clearRetry();
      if (!cur.id) cur.id = uuid();
      cur.pd = pd;
      if (pd) { cur.pa = payload.action; cur.ps = payload.source; } else { delete cur.pa; delete cur.ps; }
      writeCookie(cur);
      syncConsentId();
    };
    if (typeof w.fetch !== 'function') { fallback(1); return Promise.resolve(); }
    writeRetry(failed, now() + RETRY.grace);
    payload.consent_id = cur && cur.id ? cur.id : null;
    return w.fetch(cfg.endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      keepalive: true,
      body: JSON.stringify(payload)
    }).then((r) => {
      if (r.ok) {
        return Promise.resolve(r.json ? r.json() : {}).catch(() => ({})).then((j) => {
          if (cur && j && j.consent_id) { cur.id = j.consent_id; cur.pd = 0; delete cur.pa; delete cur.ps; }
          raw = getCookie(cname);
          clearRetry();
          const written = readState();
          if (cur && written && written.pd === 1) { cur.pd = 0; delete cur.pa; delete cur.ps; writeCookie(cur); }
          syncConsentId();
        });
      }
      if (r.status === 429 || r.status >= 500) return fallback(1);
      log('registre refusé (' + r.status + ')');
      return fallback(0);
    }).catch(() => fallback(1));
  };
  const payloadFor = (st, action, source) => ({
    format: 1,
    consent_id: null,
    action,
    source,
    config_version: cfg.version,
    served_hash: cfg.hash,
    locale: cfg.locale,
    path: w.location.pathname.slice(0, 255),
    purposes: Object.assign({}, st.p),
    services: Object.assign({}, st.s),
    exempt: Object.assign({}, st.x)
  });
  const wait = () => inflight;

  const commit = (action, source, p, s, x, anchor) => {
    const prev = cur;
    const ae = d.activeElement;
    const lost = !ae || ae === d.body || !!(ae.closest && ae.closest('[data-ccm-banner],[data-ccm-panel],[data-ccm-placeholder-box]'));
    const next = { f: 1, id: prev && prev.id ? prev.id : null, ver: cfg.version, t: now(), p, s, x, pd: 0 };
    const reload = apply(prev, next, true);
    mem = next;
    if (mode === 'preview') writeCookie(next);
    // Live: the choice is written now, as pending, so that a click followed by a quick navigation does not show the banner again; the answer of the server confirms it.
    else if (mode === 'live') { next.pd = 1; next.pa = action; next.ps = source; writeCookie(next); }
    const back = opener;
    closePanel(false);
    syncUi();
    pushEvent('ccm_consent_update');
    fire('ccm:consent', snapshot(next));
    changeCbs.slice().forEach((cb) => { try { cb(snapshot(next)); } catch (e) { log('onChange : ' + e.message); } });
    sayLive(action === 'service_allow' ? 'allowed' : 'saved');
    if (action === 'service_allow') focusUnlocked(anchor);
    else if (lost) refocus(back);
    const pl = payloadFor(next, action, source);
    inflight = inflight.then(() => send(pl));
    if (reload) inflight.then(() => api.__reload());
    return inflight;
  };
  // After a choice the banner disappears: focus goes back to the trigger, else to the link that reopens the preferences.
  const refocus = (back) => {
    const free = (el) => el.isConnected && visible(el) && !el.closest('[data-ccm-banner],[data-ccm-panel],[data-ccm-placeholder],[data-ccm-placeholder-box]');
    const t = back && free(back) ? back : $$('[data-ccm-open],a[href$="#ccm-preferences"]').filter(free)[0];
    // No preventScroll: the focused element is brought on screen, so the next Tab starts from a visible place.
    if (t && typeof t.focus === 'function') { try { t.focus(); } catch (e) { /* noop */ } }
  };
  const focusUnlocked = (anchor) => {
    const r = restored.find((x) => x.frame.isConnected && anchor && x.ph === anchor);
    let t = r ? r.frame : null;
    const holder = anchor && (anchor.isConnected ? anchor : null) || null;
    if (!t && holder) t = holder.querySelector('iframe') || holder;
    if (!t) return;
    if (t.tagName !== 'IFRAME' && !t.matches(FOCUSABLE)) t.setAttribute('tabindex', '-1');
    try { t.focus(); } catch (e) { /* noop */ }
  };

  // ---- actions ------------------------------------------------------------
  const uniform = (v) => {
    const p = {}, s = {}, x = {};
    codes().forEach((c) => { p[c] = v; });
    cfg.services.forEach((o) => { s[o.key] = v; });
    cfg.exempt.forEach((o) => { x[o.key] = v; });
    return { p, s, x };
  };
  const derive = (s, p0) => {
    const p = {};
    codes().forEach((c) => {
      const l = servicesOf(c), n = l.filter((o) => s[o.key] === 1).length;
      p[c] = l.length ? (n === l.length ? 1 : n ? 2 : 0) : (p0 && p0[c] === 1 ? 1 : 0);
    });
    return p;
  };
  const classify = (s) => {
    const prev = cur ? cfg.services.filter((o) => cur.s[o.key] === 1).length : 0;
    const gain = cfg.services.some((o) => s[o.key] === 1 && !granted(cur, o.key));
    const lost = cfg.services.some((o) => granted(cur, o.key) && s[o.key] !== 1);
    return prev && lost && !gain ? 'withdraw' : 'custom';
  };
  const keepX = (v) => {
    const x = {};
    cfg.exempt.forEach((o) => { x[o.key] = cur && cur.x[o.key] !== undefined ? cur.x[o.key] : (v === undefined ? 1 : v); });
    return x;
  };
  const readUi = () => {
    const s = {}, p0 = {};
    $$('[role="switch"][data-ccm-purpose]').forEach((el) => { p0[el.getAttribute('data-ccm-purpose')] = isOn(el) ? 1 : 0; });
    cfg.services.forEach((o) => {
      const el = $('[role="switch"][data-ccm-service="' + o.key + '"]');
      s[o.key] = el ? (isOn(el) ? 1 : 0) : (p0[o.purpose] !== undefined ? p0[o.purpose] : (granted(fresh(cur), o.key) ? 1 : 0));
    });
    const x = keepX();
    $$('[role="switch"][data-ccm-exempt]').forEach((el) => { x[el.getAttribute('data-ccm-exempt')] = isOn(el) ? 1 : 0; });
    return { s, p: derive(s, p0), x };
  };
  const exemptOnly = (x) => cfg.exempt.some((o) => cur && cur.x[o.key] !== 0 && x[o.key] === 0);
  const srcOf = (el) => (el && el.closest('[data-ccm-panel]') ? 'preferences' : el && el.closest('[data-ccm-placeholder],[data-ccm-placeholder-box]') ? 'placeholder' : 'banner');

  const allowService = (key, source, anchor) => {
    const s = svc(key);
    if (!s) return;
    const first = !fresh(cur);
    const p = {}, sv = {};
    codes().forEach((c) => { p[c] = first ? 0 : (cur.p[c] || 0); });
    cfg.services.forEach((o) => { sv[o.key] = first ? 0 : (cur.s[o.key] === 1 ? 1 : 0); });
    Object.keys(cur ? cur.s : {}).forEach((k) => { if (sv[k] === undefined && !first) sv[k] = cur.s[k]; });
    sv[key] = 1;
    if (p[s.purpose] !== 1) p[s.purpose] = 2;
    return commit('service_allow', source, p, sv, keepX(), anchor);
  };
  // A choice made while the banner is shown again (expiry, substantial change, new service) is recorded as a renewal; the real choice stays in the states.
  const renewing = () => !!cur && needsBanner(cur);
  const dispatch = (name, el) => {
    const src = srcOf(el);
    if (name === 'open' || name === 'customize') return openPanel(el);
    if (name === 'close') return closePanel(true);
    if (name === 'accept_all') { const u = uniform(1); return commit(renewing() ? 'renew' : 'accept_all', src, u.p, u.s, u.x); }
    if (name === 'reject_all' || name === 'close_reject') { const u = uniform(0); return commit(renewing() ? 'renew' : name, src, u.p, u.s, u.x); }
    if (name === 'withdraw') { const u = uniform(0); return commit('withdraw', src, u.p, u.s, u.x); }
    if (name === 'save') {
      const u = readUi();
      return commit(renewing() ? 'renew' : exemptOnly(u.x) && !changedServices(u.s) ? 'exempt_optout' : classify(u.s), src, u.p, u.s, u.x);
    }
    if (name === 'exempt_optout') {
      const k = el && el.getAttribute('data-ccm-exempt-key'), x = keepX();
      cfg.exempt.forEach((o) => { if (!k || k === o.key) x[o.key] = 0; });
      const base = cur || uniform(0);
      return commit('exempt_optout', src, Object.assign({}, base.p), Object.assign({}, base.s), x);
    }
    if (name === 'renew') {
      if (!cur) return;
      return commit('renew', src, Object.assign({}, cur.p), Object.assign({}, cur.s), Object.assign({}, cur.x));
    }
    if (name === 'service_allow') {
      const key = el.getAttribute('data-ccm-service') || (el.closest('[data-ccm-placeholder]') || el).getAttribute('data-ccm-placeholder');
      const anchor = el.closest('[data-ccm-placeholder]') || el.closest('[data-ccm-placeholder-box]');
      return allowService(key, 'placeholder', anchor ? (anchor.parentNode && !anchor.hasAttribute('data-ccm-placeholder') ? anchor.parentNode : anchor) : null);
    }
  };
  const changedServices = (s) => cfg.services.some((o) => (granted(cur, o.key) ? 1 : 0) !== s[o.key]);

  const toggle = (el) => {
    if (el.tagName !== 'INPUT') setSwitch(el, !isOn(el));
    const v = isOn(el);
    const pc = el.getAttribute('data-ccm-purpose'), sk = el.getAttribute('data-ccm-service');
    if (pc) {
      servicesOf(pc).forEach((o) => $$('[role="switch"][data-ccm-service="' + o.key + '"]').forEach((e) => setSwitch(e, v)));
    } else if (sk && svc(sk)) {
      const l = servicesOf(svc(sk).purpose);
      const n = l.filter((o) => { const e = $('[role="switch"][data-ccm-service="' + o.key + '"]'); return e ? isOn(e) : granted(fresh(cur), o.key); }).length;
      $$('[role="switch"][data-ccm-purpose="' + svc(sk).purpose + '"]').forEach((e) => setSwitch(e, n === l.length ? true : n ? 'mixed' : false));
    }
  };

  // ---- report of the Verification page (audit mode) -----------------------
  const auditPhase = () => (new URLSearchParams(w.location.search).get('ccm_audit_phase') === 'after_reject' ? 'after_reject' : 'before');
  const seen = [];
  const observe = () => {
    if (mode !== 'audit') return;
    try { w.performance.setResourceTimingBufferSize(2000); } catch (e) { /* noop */ }
    try {
      new w.PerformanceObserver((l) => l.getEntries().forEach((e) => seen.push(e.name))).observe({ type: 'resource', buffered: true });
    } catch (e) { /* observateur indisponible : le tampon de Resource Timing reste lu */ }
  };
  const auditReport = () => {
    const uniq = (a) => a.filter((v, i) => v && a.indexOf(v) === i);
    const hosts = [];
    seen.forEach((n) => { try { hosts.push(new URL(n).host); } catch (x) { /* url invalide */ } });
    try {
      w.performance.getEntriesByType('resource').forEach((e) => {
        try { hosts.push(new URL(e.name).host); } catch (x) { /* url invalide */ }
      });
    } catch (e) { /* Resource Timing indisponible */ }
    const keys = (t) => { try { return Object.keys(w[t]); } catch (e) { return []; } };
    const tags = { gtag_js: [], gtm_js: [] };
    $$('script[src]').forEach((sc) => {
      try {
        const u = new URL(sc.src), id = u.searchParams.get('id');
        if (/\/gtag\/js$/.test(u.pathname)) tags.gtag_js.push(id);
        else if (/\/gtm\.js$/.test(u.pathname)) tags.gtm_js.push(id);
      } catch (e) { /* noop */ }
    });
    return {
      source: 'ccm-audit', format: 1, phase: auditPhase(), url: w.location.href, version: cfg.version, hash: cfg.hash, mode: 'audit',
      hosts: uniq(hosts).filter((h) => h !== w.location.host),
      iframes: uniq($$('iframe').map((f) => f.src).filter((x) => x && x !== 'about:blank')),
      cookies: uniq(d.cookie.split('; ').map((c) => c.split('=')[0])),
      storage: { local: keys('localStorage'), session: keys('sessionStorage') },
      google_tags: { gtag_js: uniq(tags.gtag_js), gtm_js: uniq(tags.gtm_js) }
    };
  };
  // Fonts requested by a stylesheet of another origin do not show up in Resource Timing: their host is read in the CSS.
  const fontHosts = () => {
    const links = $$('link[rel~="stylesheet"][href]').filter((l) => { try { return new URL(l.href).host !== w.location.host; } catch (e) { return false; } });
    if (!links.length || typeof w.fetch !== 'function') return Promise.resolve([]);
    const loaded = [], known = !!(d.fonts && typeof d.fonts.forEach === 'function');
    if (known) d.fonts.forEach((f) => { if (f.status === 'loaded') loaded.push(String(f.family).replace(/["']/g, '').trim()); });
    return Promise.all(links.map((l) => Promise.resolve().then(() => w.fetch(l.href, { mode: 'cors', credentials: 'omit' })).then((r) => r.text()).catch(() => ''))).then((css) => {
      const out = [];
      css.forEach((t) => (t.match(/@font-face\s*\{[^}]*\}/g) || []).forEach((b) => {
        const fam = (b.match(/font-family:\s*['"]?([^;'"]+)/) || [])[1], u = b.match(/url\(\s*['"]?(https?:[^)'"\s]+)/);
        if (!u || !fam || (known && loaded.indexOf(fam.trim()) < 0)) return;
        try { out.push(new URL(u[1]).host); } catch (e) { /* url invalide */ }
      }));
      return out;
    });
  };
  let reported = false;
  const scheduleAudit = () => {
    const parent = api.__parent();
    if (mode !== 'audit' || !parent || parent === w) return;
    const go = () => setTimeout(() => {
      if (reported) return;
      reported = true;
      fontHosts().catch(() => []).then((extra) => {
        try {
          const r = auditReport();
          r.hosts = r.hosts.concat(extra.filter((h) => h !== w.location.host && r.hosts.indexOf(h) < 0).filter((h, i, l) => l.indexOf(h) === i));
          parent.postMessage(r, w.location.origin);
        } catch (e) { log('postMessage : ' + e.message); }
      });
    }, api.__auditDelay);
    if (d.readyState === 'complete') go(); else w.addEventListener('load', go, { once: true });
  };

  // ---- lifecycle --------------------------------------------------------
  const resync = () => {
    if (!started || mode === 'audit') return;
    const v = getCookie(cname);
    if (v === raw) return;
    const next = v ? decode(v) : null, prev = cur;
    raw = v;
    const reload = apply(prev, next, false);
    changeCbs.slice().forEach((cb) => { try { cb(snapshot(next)); } catch (e) { /* noop */ } });
    if (reload) api.__reload();
  };
  const bind = () => {
    if (bound) return;
    bound = true;
    d.addEventListener('click', (e) => {
      const t = e.target;
      if (!t || !t.closest) return;
      const a = t.closest('[data-ccm-action]');
      if (a) { e.preventDefault(); dispatch(a.getAttribute('data-ccm-action'), a); return; }
      const sw = t.closest('[role="switch"]');
      if (sw && (sw.hasAttribute('data-ccm-purpose') || sw.hasAttribute('data-ccm-service') || sw.hasAttribute('data-ccm-exempt'))) { toggle(sw); return; }
      const o = t.closest('[data-ccm-open],a[href$="#ccm-preferences"]');
      if (o) { e.preventDefault(); openPanel(o); }
    });
    d.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && panelOpen) { e.preventDefault(); closePanel(true); return; }
      const t = e.target;
      if ((e.key === ' ' || e.key === 'Enter') && t && t.getAttribute && t.getAttribute('role') === 'switch' && t.tagName !== 'BUTTON' && t.tagName !== 'INPUT' && t.closest && !t.closest('[data-ccm-action]')) {
        e.preventDefault(); toggle(t); return;
      }
      if (e.key === 'Tab' && activeModal && activeModal.isConnected) {
        const f = focusables(activeModal);
        if (!f.length) { e.preventDefault(); return; }
        const first = f[0], last = f[f.length - 1], ae = d.activeElement;
        if (!activeModal.contains(ae)) { e.preventDefault(); first.focus(); }
        else if (e.shiftKey && ae === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && ae === last) { e.preventDefault(); first.focus(); }
      }
    });
    d.addEventListener('turbo:load', () => {
      panelOpen = false;
      if (activeModal && !activeModal.isConnected) activeModal = null;
      restored.splice(0, restored.length, ...restored.filter((r) => r.frame.isConnected));
      cur = readState();
      previewNote();
      syncUi();
    });
    w.addEventListener('resize', () => { if (ready) syncBanner(); });
    w.addEventListener('pageshow', (e) => { if (e.persisted) resync(); });
    d.addEventListener('visibilitychange', () => { if (d.visibilityState === 'visible') resync(); });
    if (w.MutationObserver) {
      new w.MutationObserver((ms) => {
        const hit = ms.some((m) => Array.from(m.addedNodes).some((n) => n.nodeType === 1 && (n.matches('[data-ccm-placeholder],script[type="text/plain"]') || n.querySelector('[data-ccm-placeholder],script[type="text/plain"]'))));
        if (hit) { syncPlaceholders(); syncScripts(); }
      }).observe(d.documentElement, { childList: true, subtree: true });
    }
  };

  const api = w.CookieConsentManager = {
    open() { openPanel(d.activeElement); },
    hasConsent(k) {
      if (cfg && cfg.exempt.some((e) => e.key === k)) return !cur || cur.x[k] !== 0;
      return granted(fresh(cur), k);
    },
    hasPurposeConsent(c) { return !!fresh(cur) && cur.p[c] === 1; },
    onConsent(k, cb) {
      if (!ready) { pre.push(() => api.onConsent(k, cb)); return; }
      if (granted(cur, k) && on(cur, k)) { try { cb(); } catch (e) { log('onConsent ' + k + ' : ' + e.message); } }
      (cbs[k] = cbs[k] || []).push(cb);
    },
    onChange(cb) {
      changeCbs.push(cb);
      return () => { const i = changeCbs.indexOf(cb); if (i >= 0) changeCbs.splice(i, 1); };
    },
    setConsent(o) {
      if (!ready) { pre.push(() => api.setConsent(o)); return Promise.resolve(); }
      o = o || {};
      const s = {}, p0 = {};
      cfg.services.forEach((x) => { s[x.key] = granted(cur, x.key) ? 1 : 0; });
      Object.keys(o.purposes || {}).forEach((c) => {
        p0[c] = o.purposes[c];
        if (o.purposes[c] !== 2) servicesOf(c).forEach((x) => { s[x.key] = o.purposes[c] === 1 ? 1 : 0; });
      });
      Object.keys(o.services || {}).forEach((k) => { if (svc(k)) s[k] = o.services[k] === 1 ? 1 : 0; });
      const x = keepX();
      Object.keys(o.exempt || {}).forEach((k) => { x[k] = o.exempt[k] === 0 ? 0 : 1; });
      const p = derive(s, Object.assign(cur ? cur.p : {}, p0));
      return commit(classify(s), 'api', p, s, x);
    },
    getConsentId() { return cur && cur.id ? cur.id : null; },
    __reload() { w.location.reload(); },
    __parent() { return w.parent; },
    __auditDelay: 3000,
    __wait: wait,
    __boot() {
      const el = d.getElementById('ccm-config');
      try { cfg = JSON.parse(el.textContent); } catch (e) { cfg = null; }
      if (!cfg || cfg.format !== 1) return;
      cfg.services = cfg.services || []; cfg.purposes = cfg.purposes || []; cfg.exempt = cfg.exempt || [];
      cfg.options = cfg.options || {}; cfg.gcm = cfg.gcm || {};
      mode = cfg.mode || 'live';
      cname = mode === 'preview' ? 'ccm_preview' : 'ccm_consent';
      w.dataLayer = w.dataLayer || [];
      const user = {};
      cfg.services.concat(cfg.exempt).forEach((s) => Object.assign(user, s.user || {}));
      adapter = {
        locale: String(cfg.locale || 'en').slice(0, 2),
        user,
        lang: { allow: text('compat_allow'), fallback: text('compat_fallback') },
        isAllowed: (k) => on(cur, k),
        placeholderHtml,
        allow: (k) => { allowService(k, 'placeholder', null); },
        log
      };
      const t = w.CCMCompat && typeof w.CCMCompat.create === 'function' ? w.CCMCompat.create(adapter) : {};
      w.tarteaucitron = t;
      t.user = user;
      t.services = {};
      w.timeExpire = 34214400000;
      observe();
    },
    __start() {
      if (!cfg || started) return;
      started = true;
      cur = readState();
      if (cur && cur.f !== 1) cur = null;
      if (mode === 'audit' && auditPhase() === 'after_reject') {
        const u = uniform(0);
        cur = mem = { f: 1, id: null, ver: cfg.version, t: now(), p: u.p, s: u.s, x: u.x, pd: 0 };
      }
      bind();
      previewNote();
      const adv = cfg.gcm.enabled && cfg.gcm.mode === 'advanced';
      if (adv) cfg.services.forEach((s) => { if (s.googleTag && s.kind === 'script') runService(s); });
      apply(null, cur, false);
      if (cur && cur.pd === 1 && mode === 'live') {
        const all = codes().every((c) => cur.p[c] === 1), none = codes().every((c) => !cur.p[c]);
        const pl = payloadFor(cur, cur.pa || (all ? 'accept_all' : none ? 'reject_all' : 'custom'), cur.ps || 'preferences');
        const retry = readRetry();
        if (!retry || retry.next <= now()) inflight = inflight.then(() => send(pl, true));
      }
      ready = true;
      syncUi();
      const q = w.ccmQueue;
      w.ccmQueue = { push(fn) { try { fn(); } catch (e) { log('ccmQueue : ' + e.message); } return 1; } };
      if (Array.isArray(q)) q.forEach((fn) => w.ccmQueue.push(fn));
      pre.splice(0).forEach((fn) => fn());
      fire('ccm:ready', snapshot(cur));
      scheduleAudit();
    }
  };
})(window, document);
