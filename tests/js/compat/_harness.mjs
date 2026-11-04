// Shared harness: loads compat.js + a catalogue fragment into jsdom behind a Proxy that rejects
// every access outside the ADR-001 v2 contract, then runs fallback() and js().
import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { JSDOM } from 'jsdom';

export const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');
export const compatSource = readFileSync(join(ROOT, 'assets/js/compat.js'), 'utf8');
export const catalogue = JSON.parse(readFileSync(join(ROOT, 'Resources/catalogue/catalogue.json'), 'utf8'));
export const readFragment = (key) => readFileSync(join(ROOT, 'Resources/catalogue/fragments', `${key}.js`), 'utf8');

export const CONTRACT = new Set([
  'services', 'user', 'lang', 'job', 'state', 'parameters', 'isAjax', 'cookie',
  'getElemAttr', 'addScript', 'addInternalScript', 'fallback', 'engage', 'getStyleSize',
  'getElemWidth', 'getElemHeight', 'getLocale', 'getLanguage', 'extend', 'fixSelfXSS',
]);
export const PARAMETERS = new Set([
  'googleConsentMode', 'bingConsentMode', 'piwikConsentMode', 'pianoConsentMode', 'softConsentMode', 'useExternalJs',
]);

export class ContractViolation extends Error {}

export function fallbackClasses(code) {
  const classes = new Set();
  for (const m of code.matchAll(/tarteaucitron\.fallback\(\s*\[([^\]]*)\]/g)) {
    for (const c of m[1].matchAll(/["']([^"']+)["']/g)) classes.add(c[1]);
  }
  return [...classes];
}

function guard(real, violations, spies) {
  const parameters = new Proxy(real.parameters, {
    get(target, prop, receiver) {
      if (typeof prop === 'symbol') return Reflect.get(target, prop, receiver);
      if (!PARAMETERS.has(prop)) {
        violations.push(`parameters.${prop}`);
        throw new ContractViolation(`access outside contract: tarteaucitron.parameters.${prop}`);
      }
      return target[prop];
    },
  });
  return new Proxy(real, {
    get(target, prop, receiver) {
      if (typeof prop === 'symbol') return Reflect.get(target, prop, receiver);
      if (!CONTRACT.has(prop)) {
        violations.push(prop);
        throw new ContractViolation(`access outside contract: tarteaucitron.${prop}`);
      }
      if (prop === 'parameters') return parameters;
      if (prop === 'addScript' || prop === 'addInternalScript') {
        return (...args) => { spies.addScript.push(args); return target.addScript(...args); };
      }
      return target[prop];
    },
    set(target, prop, value) {
      if (typeof prop !== 'symbol' && !CONTRACT.has(prop)) {
        violations.push(`set ${prop}`);
        throw new ContractViolation(`write outside contract: tarteaucitron.${prop}`);
      }
      target[prop] = value;
      return true;
    },
  });
}

/**
 * Runs one service. Options: fragment (override source), user (values for tarteaucitron.user),
 * timeExpire (set the bare global, default true), runCallbacks (call addScript callbacks).
 */
export function runService(key, options = {}) {
  const fragment = options.fragment ?? readFragment(key);
  const classes = fallbackClasses(fragment);
  const markup = classes
    .map((c) => `<div class="${c}" data-videoID="abc123" data-url="https://example.com/embed" data-id="PB123" id="el-${c}" data-width="640" data-height="360" width="640" height="360"></div>`)
    .join('');
  const dom = new JSDOM(`<!doctype html><html lang="fr"><head><script>/* anchor */</script></head><body>${markup}</body></html>`, {
    url: 'https://shop.example.test/', runScripts: 'outside-only',
  });
  const { window } = dom;
  const result = { key, violations: [], errors: [], jsCallsDuringFallback: 0, scriptsDuringFallback: 0, jsCalls: 0, scripts: [], placeholders: [], allowed: [], callbackErrors: [], window };
  const spies = { addScript: result.scripts };
  try {
    if (options.timeExpire !== false) window.timeExpire = 31536000000;
    window.eval(compatSource);
    const adapter = {
      locale: 'fr',
      user: { ...(options.user ?? {}) },
      isAllowed: () => true,
      placeholderHtml: (k) => { result.placeholders.push(k); return `<div data-ccm-placeholder-box="${k}"><button type="button" data-ccm-action="service_allow">Autoriser</button></div>`; },
      allow: (k) => result.allowed.push(k),
      log: () => {},
    };
    const real = window.CCMCompat.create(adapter);
    window.tarteaucitron = guard(real, result.violations, spies);
    window.eval(fragment);
    const service = real.services[key];
    if (!service) throw new Error(`fragment did not register services.${key}`);
    const originalJs = service.js;
    service.js = function (...args) { result.jsCalls += 1; return originalJs.apply(this, args); };
    result.service = service;

    const attempt = (label, fn) => {
      try { fn(); } catch (error) {
        if (error instanceof ContractViolation) return;
        result.errors.push(`${label}: ${error.name}: ${error.message}`);
      }
    };
    if (typeof service.fallback === 'function') {
      attempt('fallback', () => service.fallback());
      result.jsCallsDuringFallback = result.jsCalls;
      result.scriptsDuringFallback = result.scripts.length;
      result.jsCalls = 0;
    }
    attempt('js', () => service.js());
    if (options.runCallbacks) {
      for (const args of result.scripts) {
        if (typeof args[2] !== 'function') continue;
        try { args[2](); } catch (error) {
          if (!(error instanceof ContractViolation)) result.callbackErrors.push(`${error.name}: ${error.message}`);
        }
      }
    }
  } catch (error) {
    result.errors.push(`harness: ${error.name}: ${error.message}`);
  }
  return result;
}

export function dispose(result) {
  result.window.close();
}
