(function (w, d) {
  w.ccmQueue = w.ccmQueue || [];
  w.dataLayer = w.dataLayer || [];
  w.gtag = w.gtag || function () { w.dataLayer.push(arguments); };
  var c, j = null, e = d.getElementById('ccm-config');
  try { c = JSON.parse(e.textContent); } catch (x) { return; }
  if (!c || c.format !== 1) return;
  var g = c.gcm || {}, P = c.purposes || [], S = c.services || [], H = d.documentElement;
  H.classList.add('ccm-js');
  if (!d.getElementById('ccm-critical')) {
    e = d.createElement('style');
    e.id = 'ccm-critical';
    e.textContent = 'html.ccm-show [data-ccm-banner]{display:block}';
    (d.head || H).appendChild(e);
  }
  if (g.enabled && !w.__ccmGcmDefault) {
    w.__ccmGcmDefault = 1;
    (g.defaults || []).forEach(function (o) {
      var r = {}, k;
      for (k in o) if (k !== 'region') r[k] = o[k];
      if (o.region && o.region.length) r.region = o.region;
      if (g.waitForUpdate > 0) r.wait_for_update = g.waitForUpdate;
      w.gtag('consent', 'default', r);
    });
    if (g.urlPassthrough) w.gtag('set', 'url_passthrough', true);
    if (g.adsDataRedaction) w.gtag('set', 'ads_data_redaction', true);
    if (g.uet) (w.uetq = w.uetq || []).push('consent', 'default', { ad_storage: 'denied' });
  }
  if (c.mode !== 'audit') {
    try {
      var m = d.cookie.match(new RegExp('(?:^|; )' + (c.mode === 'preview' ? 'ccm_preview' : 'ccm_consent') + '=([^;]*)'));
      if (m) {
        var v = m[1].replace(/-/g, '+').replace(/_/g, '/');
        while (v.length % 4) v += '=';
        j = JSON.parse(decodeURIComponent(atob(v).split('').map(function (c) { return '%' + ('0' + c.charCodeAt(0).toString(16)).slice(-2); }).join('')));
        if (!j || j.f !== 1) j = null;
      }
    } catch (x) { j = null; }
  }
  var p = (j && j.p) || {}, s = (j && j.s) || {}, show = !j;
  if (j) {
    // An expired, undated or outdated choice is no consent: Consent Mode stays at its default until the visitor answers.
    var stale = typeof j.t !== 'number' || j.t % 1 !== 0 || j.t + c.repromptDays * 86400 <= Date.now() / 1e3 || (c.substantialVersion > 0 && (j.ver || 0) < c.substantialVersion);
    show = stale || S.some(function (o) { return s[o.key] === undefined && p[o.purpose] === 1; });
    if (stale) { p = {}; s = {}; }
    if (g.enabled && !stale) {
      var u = {}, a = {};
      (g.defaults || []).forEach(function (o) {
        for (var k in o) if (k !== 'region' && k !== 'wait_for_update' && k !== 'security_storage') u[k] = 'denied';
      });
      P.forEach(function (o) {
        (o.signals || []).forEach(function (n) {
          u[n] = u[n] === 'granted' || p[o.code] === 1 ? 'granted' : 'denied';
          if (n === 'ad_storage') a.ad_storage = u[n];
        });
      });
      w.gtag('consent', 'update', u);
      if (g.uet && a.ad_storage) (w.uetq = w.uetq || []).push('consent', 'update', a);
    }
  }
  H.classList[show ? 'add' : 'remove']('ccm-show');
  w.dataLayer.push({ event: 'ccm_consent_ready', ccm: { purposes: p, services: s, version: c.version } });
})(window, document);
