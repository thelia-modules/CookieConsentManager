/*
 * CookieConsentManager: tarteaucitron compatibility layer (decision record docs/decisions/001-service-catalogue.md, contract in docs/runtime-contracts.md).
 * Exposes window.CCMCompat.create(engineAdapter), which returns the `tarteaucitron` object the
 * vendored service fragments are written against. Only the surface listed in that decision record exists
 * here; the engine owns window.tarteaucitron and window.timeExpire.
 *
 * getElemAttr, fixSelfXSS, getStyleSize, getElemWidth/Height, fallback, extend, addScript are
 * faithful to tarteaucitron.js v1.37.0 (copyright (c) 2014 AmauriC, MIT licence).
 */
(function () {
  'use strict';

  var LOCALES = { fr: 'fr_FR', en: 'en_US', de: 'de_DE', es: 'es_ES', it: 'it_IT', pt: 'pt_PT', nl: 'nl_NL', el: 'el_EL' };
  var DEFAULT_LANG = { allow: 'Allow', fallback: 'is disabled.' };
  var STYLE_UNITS = ['px', '%', 'em', 'rem', 'vh', 'vw', 'vmin', 'vmax', 'ch', 'ex', 'pt', 'pc', 'cm', 'mm', 'in', 'q'];
  var STYLE_PATTERN = new RegExp('^\\d+(\\.\\d+)?(' + STYLE_UNITS.join('|') + ')$');

  var activeListener = null;

  function installAllowListener(adapter) {
    if (activeListener) {
      document.removeEventListener('click', activeListener, true);
      activeListener = null;
    }
    activeListener = function (event) {
      var target = event.target;
      if (!target || typeof target.closest !== 'function') { return; }
      var button = target.closest('[data-ccm-action="service_allow"]');
      if (!button) { return; }
      var box = button.closest('[data-ccm-placeholder-box]');
      if (!box) { return; }
      var key = box.getAttribute('data-ccm-placeholder-box') || box.getAttribute('data-ccm-service') || '';
      if (key === '') { return; }
      event.preventDefault();
      event.stopPropagation();
      adapter.allow(key);
    };
    document.addEventListener('click', activeListener, true);
  }

  function expireCookie(name, domain) {
    document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' + (domain ? '; domain=' + domain : '');
  }

  function purgeCookie(name) {
    var host = location.hostname;
    var labels = host.split('.');
    var domains = [null, host];
    for (var i = 1; i < labels.length - 1; i += 1) { domains.push(labels.slice(i).join('.')); }
    domains.forEach(function (domain) {
      expireCookie(name, domain);
      if (domain) { expireCookie(name, '.' + domain); }
    });
  }

  function create(engineAdapter) {
    var adapter = engineAdapter || {};
    var language = typeof adapter.locale === 'string' && adapter.locale !== '' ? adapter.locale.slice(0, 2) : 'en';
    var texts = adapter.lang && typeof adapter.lang === 'object' ? adapter.lang : DEFAULT_LANG;
    var tarteaucitron = {};

    if (typeof adapter.allow === 'function') { installAllowListener(adapter); }

    tarteaucitron.services = {};
    tarteaucitron.user = adapter.user || {};
    tarteaucitron.lang = { allow: texts.allow || DEFAULT_LANG.allow, fallback: texts.fallback || DEFAULT_LANG.fallback };
    tarteaucitron.job = [];
    tarteaucitron.state = {};
    tarteaucitron.isAjax = false;
    tarteaucitron.parameters = Object.freeze({
      googleConsentMode: false,
      bingConsentMode: false,
      piwikConsentMode: false,
      pianoConsentMode: false,
      softConsentMode: true,
      useExternalJs: false
    });

    tarteaucitron.cookie = {
      purge: function (names) {
        if (!Array.isArray(names)) { return; }
        var present = document.cookie.split(';').map(function (c) { return c.split('=')[0].trim(); });
        names.forEach(function (name) {
          if (typeof name !== 'string' || name === '') { return; }
          if (name.indexOf('*') === -1) { purgeCookie(name); return; }
          var prefix = name.slice(0, name.indexOf('*'));
          present.forEach(function (candidate) { if (candidate.indexOf(prefix) === 0) { purgeCookie(candidate); } });
        });
      }
    };

    tarteaucitron.fixSelfXSS = function (html) {
      return html.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    };

    tarteaucitron.getLanguage = function () { return language; };

    tarteaucitron.getLocale = function () { return LOCALES[language] || 'en_US'; };

    tarteaucitron.getStyleSize = function (value) {
      if (value == null) { return 'auto'; }
      value = String(value).trim();
      if (STYLE_PATTERN.test(value)) { return value; }
      if (/^\d+(\.\d+)?$/.test(value)) { return value + 'px'; }
      return 'auto';
    };

    tarteaucitron.getElemAttr = function (elem, attr) {
      var attribute = elem.getAttribute('data-' + attr) || elem.getAttribute(attr) || elem.getAttribute(attr.startsWith('data-') ? attr.slice(5) : attr);

      if ((attr === 'url' || attr === 'data-url' || attr === 'data-src') && !/^https?:\/\/[^\s]+$/.test(attribute)) {
        return '';
      }
      if (attr === 'srcdoc' || attr === 'data-srcdoc') {
        attribute = elem.getAttribute('srcdoc');
      }
      if (typeof attribute === 'string') {
        return tarteaucitron.fixSelfXSS(attribute);
      }
      return '';
    };

    tarteaucitron.getElemWidth = function (elem) {
      return tarteaucitron.getElemAttr(elem, 'width') || elem.clientWidth;
    };

    tarteaucitron.getElemHeight = function (elem) {
      return tarteaucitron.getElemAttr(elem, 'height') || elem.clientHeight;
    };

    tarteaucitron.extend = function (a, b) {
      for (var prop in b) {
        if (Object.prototype.hasOwnProperty.call(b, prop)) { a[prop] = b[prop]; }
      }
    };

    tarteaucitron.addScript = function (url, id, callback, execute, attrName, attrVal) {
      var script;
      var done = false;

      if (execute === false) {
        if (typeof callback === 'function') { callback(); }
        return;
      }
      script = document.createElement('script');
      if (id !== undefined && id !== '') { script.id = id; }
      script.async = true;
      script.src = url;
      if (attrName !== undefined && attrVal !== undefined) { script.setAttribute(attrName, attrVal); }
      if (typeof callback === 'function') {
        script.onreadystatechange = script.onload = function () {
          var state = script.readyState;
          if (!done && (!state || /loaded|complete/.test(state))) {
            done = true;
            callback();
          }
        };
      }
      document.getElementsByTagName('head')[0].appendChild(script);
    };

    tarteaucitron.addInternalScript = function (url, id, callback, execute, attrName, attrVal) {
      tarteaucitron.addScript(url, id, callback, execute, attrName, attrVal);
    };

    tarteaucitron.fallback = function (matchClass, content, noInner) {
      var selector = matchClass.map(function (cls) { return '.' + cls; }).join(', ');
      var elems = document.querySelectorAll(selector);

      for (var i = 0; i < elems.length; i += 1) {
        var elem = elems[i];
        var width = tarteaucitron.getElemAttr(elem, 'width');
        var height = tarteaucitron.getElemAttr(elem, 'height');

        if (width !== '') { elem.style.width = tarteaucitron.getStyleSize(width); }
        if (height !== '') { elem.style.height = tarteaucitron.getStyleSize(height); }

        if (typeof content === 'function') {
          if (noInner === true) {
            content(elem);
          } else {
            elem.innerHTML = content(elem);
          }
        } else {
          elem.innerHTML = content;
        }
      }
    };

    tarteaucitron.engage = function (id) {
      return adapter.placeholderHtml(id);
    };

    return tarteaucitron;
  }

  window.CCMCompat = { create: create };
}());
