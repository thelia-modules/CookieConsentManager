import vm from 'node:vm';
import { EXCLUDED_BY_KEY } from './purposes.mjs';

export const PLACEHOLDER = 'CCMPH';

export const FROZEN_PARAMETERS = {
  googleConsentMode: false,
  bingConsentMode: false,
  piwikConsentMode: false,
  pianoConsentMode: false,
  softConsentMode: true,
  useExternalJs: false,
};

function makeUser(mode) {
  const value = () => (mode === 'array' ? [PLACEHOLDER] : PLACEHOLDER);
  return new Proxy({}, {
    get: (_t, prop) => (typeof prop === 'symbol' ? undefined : value()),
    has: () => true,
  });
}

function run(fragment, key, mode) {
  const sandbox = {
    tarteaucitron: { services: {}, user: makeUser(mode), parameters: { ...FROZEN_PARAMETERS }, lang: {}, state: {}, job: [] },
  };
  const context = vm.createContext(sandbox, { codeGeneration: { strings: false, wasm: false } });
  new vm.Script(fragment, { filename: `${key}.js` }).runInContext(context, { timeout: 2000 });
  const service = sandbox.tarteaucitron.services[key];
  if (!service) throw new Error(`Fragment "${key}" did not define tarteaucitron.services.${key}.`);
  return service;
}

/** Evaluates a fragment against a simulated `tarteaucitron` and returns its static metadata. */
export function readMetadata(fragment, key) {
  let service;
  let firstError = null;
  for (const mode of ['string', 'array']) {
    try { service = run(fragment, key, mode); break; } catch (error) { firstError ??= error; }
  }
  if (!service) throw new Error(`Cannot evaluate fragment "${key}" in the sandbox: ${firstError.message}`);

  const cookies = Array.isArray(service.cookies) ? service.cookies.filter((c) => typeof c === 'string') : [];
  return {
    innerKey: typeof service.key === 'string' ? service.key : null,
    type: typeof service.type === 'string' ? service.type : null,
    name: typeof service.name === 'string' ? service.name : null,
    uri: typeof service.uri === 'string' ? service.uri : '',
    needConsent: service.needConsent,
    cookies: cookies.map((c) => c.split(PLACEHOLDER).join('*')),
    cookiesDynamic: cookies.some((c) => c.includes(PLACEHOLDER)),
    cookiesIife: /["']?cookies["']?\s*:\s*\(function/.test(fragment),
    hasJs: typeof service.js === 'function',
    hasFallback: typeof service.fallback === 'function',
    jsSource: typeof service.js === 'function' ? Function.prototype.toString.call(service.js) : '',
    fallbackSource: typeof service.fallback === 'function' ? Function.prototype.toString.call(service.fallback) : '',
  };
}

export function stripComments(code) {
  return code.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
}

/** Detects constructs the compatibility layer does not support. Returns the first exclusion reason or null. */
export function detectExclusion(fragment, type, key = '') {
  if (type === 'google') return 'gcm';
  if (Object.hasOwn(EXCLUDED_BY_KEY, key)) return EXCLUDED_BY_KEY[key];
  const code = stripComments(fragment);
  if (/\bmakeAsync\b/.test(code)) return 'makeAsync';
  if (/\beval\s*\(/.test(code)) return 'eval';
  if (/\bdocument\s*\.\s*write(ln)?\s*\(/.test(code)) return 'document.write';
  return null;
}

/** Names read from `tarteaucitron.user`, minus callbacks (`typeof ... === 'function'`), plus the required ones. */
export function readParams(fragment) {
  const code = stripComments(fragment);
  const names = new Set();
  for (const m of code.matchAll(/tarteaucitron\.user\.([A-Za-z0-9_]+)/g)) names.add(m[1]);
  for (const m of code.matchAll(/tarteaucitron\.user\[\s*["']([A-Za-z0-9_]+)["']\s*\]/g)) names.add(m[1]);
  for (const m of code.matchAll(/["']([A-Za-z0-9_]+)["']\s+in\s+tarteaucitron\.user\b/g)) names.add(m[1]);

  const callbacks = new Set();
  for (const m of code.matchAll(/typeof\s+tarteaucitron\.user\.([A-Za-z0-9_]+)\s*===?\s*["']function["']/g)) callbacks.add(m[1]);

  const required = new Set();
  for (const m of code.matchAll(/if\s*\(([^()]*)\)\s*\{\s*return;?\s*\}/g)) {
    const condition = m[1];
    if (condition.includes('&&')) continue;
    for (const part of condition.split('||')) {
      const eq = /tarteaucitron\.user\.([A-Za-z0-9_]+)\s*===?\s*undefined/.exec(part) || /^\s*!\s*tarteaucitron\.user\.([A-Za-z0-9_]+)\s*$/.exec(part);
      if (eq) required.add(eq[1]);
    }
  }
  const params = [...names].filter((n) => !callbacks.has(n)).sort();
  return { params, callbacks: [...callbacks].sort(), required };
}

/** Class names given as the first argument of `tarteaucitron.fallback([...])`. */
export function readFallbackClasses(fragment) {
  const classes = new Set();
  for (const m of stripComments(fragment).matchAll(/tarteaucitron\.fallback\(\s*\[([^\]]*)\]/g)) {
    for (const c of m[1].matchAll(/["']([^"']+)["']/g)) classes.add(c[1]);
  }
  return [...classes];
}
