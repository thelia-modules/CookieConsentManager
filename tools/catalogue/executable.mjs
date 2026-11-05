// Maintainer tool (Node 18, no network, needs the devDependency jsdom): tells which parameters of a catalogue fragment are
// "executable", that is whose value decides what runs on the storefront. build.mjs writes the answer into catalogue.json
// (`executable` on each parameter), the server asks for the publish right (CREATE) to change one, and a test compares
// the flags with this derivation, so a fragment that loads a parameter that is not flagged fails the tests.
//
// A parameter is executable when its value reaches:
//   - the origin of a script that gets loaded (the address starts with it, or it sits in the host part, whatever the path);
//   - the body of an inline script, or an event handler attribute of a script;
//   - markup written into the page (a quote or an angle bracket typed in it creates an element).
// A value placed after a literal host (https://cdn.example/lib.js?id=<value>) only picks a resource of that publisher: not executable.
//
// Two reads, joined (a parameter found by either one is flagged):
//   - dynamic: the fragment runs in jsdom, on the real compatibility layer, with a unique marker as the value of every parameter,
//     once with plain markers and once with markers that carry markup; js() and fallback(), softConsentMode on and off,
//     callbacks of the loaded scripts called. Where the markers end up is read in the addresses of the scripts, in the scripts
//     created, and in the elements injected into the page;
//   - static: the argument of addScript(), the right side of `.src =`, `.text =`, `innerHTML =` and `setAttribute('src', ...)`
//     are read in the source, local variables followed. It covers what the probe does not reach (a branch that needs a
//     value or an event the probe does not give).
import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { JSDOM } from 'jsdom';
import { FROZEN_PARAMETERS, stripComments } from './sandbox.mjs';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const compatSource = readFileSync(join(ROOT, 'assets/js/compat.js'), 'utf8');

const HOST_DONE = /^(?:[a-z][a-z0-9+.-]*:)?\/\/[^/?#]*[/?#]/i;
const SCRIPT_TAGS = new Set(['script']);

const markerOf = (index) => `ccmprobe${String(index).padStart(3, '0')}x`;
// Breaks out of a double-quoted and of a single-quoted attribute, then of the tag: whatever sink writes it as markup grows an
// attribute `data-ccm-p` or an element `ccmx`.
const hostileOf = (index) => `${markerOf(index)}" data-ccm-p="${index}" ${markerOf(index)}' data-ccm-p='${index}' ><ccmx data-p="${index}"></ccmx>`;

/** Does the marker sit in the origin of the address (scheme, authority), or is the address built from it from the start? */
function reachesOrigin(address, marker) {
  const at = address.indexOf(marker);
  if (at === -1) return false;
  const prefix = /^(?:[a-z][a-z0-9+.-]*:)?\/\//i.exec(address);
  if (prefix) {
    const rest = address.slice(prefix[0].length);
    const end = rest.search(/[/?#]/);
    return (end === -1 ? rest : rest.slice(0, end)).includes(marker);
  }
  const slash = address.search(/[/?#]/);
  return slash === -1 || at < slash;
}

function runProbe(fragment, key, markers, { soft, hostile, array }) {
  const found = new Map();
  const note = (name, reason) => {
    if (!found.has(name)) found.set(name, new Set());
    found.get(name).add(reason);
  };
  const byMarker = new Map(markers.map(([name, index]) => [markerOf(index), name]));
  const dom = new JSDOM('<!doctype html><html lang="fr"><head><script>/* anchor */</script></head><body></body></html>', { url: 'https://shop.example.test/', runScripts: 'outside-only' });
  const { window } = dom;
  const errors = [];
  try {
    window.timeExpire = 31536000000;
    const created = [];
    const createElement = window.document.createElement.bind(window.document);
    window.document.createElement = (tag, ...rest) => {
      const element = createElement(tag, ...rest);
      if (SCRIPT_TAGS.has(String(tag).toLowerCase())) created.push(element);
      return element;
    };
    window.eval(compatSource);
    const user = {};
    for (const [name, index] of markers) {
      const value = hostile ? hostileOf(index) : markerOf(index);
      user[name] = array ? [value] : value;
    }
    const real = window.CCMCompat.create({
      locale: 'fr', user, isAllowed: () => true, placeholderHtml: () => '', allow: () => {}, log: () => {},
    });
    const calls = [];
    window.tarteaucitron = new Proxy(real, {
      get(target, prop, receiver) {
        if (prop === 'parameters') return { ...FROZEN_PARAMETERS, softConsentMode: soft };
        if (prop === 'addScript' || prop === 'addInternalScript') {
          return (...args) => { calls.push(args); return target.addScript(...args); };
        }
        return Reflect.get(target, prop, receiver);
      },
    });
    window.eval(fragment);
    const service = real.services[key];
    if (!service) throw new Error(`fragment did not register services.${key}`);
    const attempt = (label, fn) => {
      try { fn(); } catch (error) { errors.push(`${label}: ${error.name}: ${error.message}`); }
    };
    if (typeof service.fallback === 'function') attempt('fallback', () => service.fallback());
    if (typeof service.js === 'function') attempt('js', () => service.js());
    for (let round = 0; round < 3; round += 1) {
      for (const args of calls.splice(0)) {
        for (const [marker, name] of byMarker) {
          if (reachesOrigin(String(args[0]), marker)) note(name, `addScript address ${String(args[0])}`);
          if (String(args[4] ?? '').toLowerCase().startsWith('on') && String(args[5] ?? '').includes(marker)) note(name, `addScript handler ${args[4]}`);
        }
        if (typeof args[2] === 'function') attempt('callback', () => args[2]());
      }
    }

    for (const element of created) {
      for (const [marker, name] of byMarker) {
        if (reachesOrigin(element.getAttribute('src') ?? '', marker)) note(name, `script src ${element.getAttribute('src')}`);
        if ((element.text ?? '').includes(marker)) note(name, 'script body');
        for (const attribute of element.attributes) {
          if (attribute.name.toLowerCase().startsWith('on') && attribute.value.includes(marker)) note(name, `script handler ${attribute.name}`);
        }
      }
    }
    if (hostile) {
      for (const element of window.document.querySelectorAll('ccmx, [data-ccm-p]')) {
        const index = Number(element.getAttribute('data-p') ?? element.getAttribute('data-ccm-p'));
        const owner = markers.find(([, i]) => i === index)?.[0];
        if (owner) note(owner, 'markup injected into the page');
      }
    }
  } catch (error) {
    errors.push(`probe: ${error.name}: ${error.message}`);
  } finally {
    window.close();
  }
  return { found, errors };
}

// ---- static read -------------------------------------------------------------------------------------------------

function isQuoted(text) {
  return /^(['"])(?:\\.|(?!\1)[^\\])*\1$/.test(text);
}

function unwrap(text) {
  let t = text.trim();
  while (t.startsWith('(') && closing(t, 0) === t.length - 1) t = t.slice(1, -1).trim();
  return t;
}

/** Index of the bracket that closes the one at `open`, strings skipped; -1 when none. */
function closing(text, open) {
  const pairs = { '(': ')', '[': ']', '{': '}' };
  const stack = [];
  for (let i = open; i < text.length; i += 1) {
    const c = text[i];
    if (c === '"' || c === "'" || c === '`') {
      i += 1;
      while (i < text.length && text[i] !== c) i += text[i] === '\\' ? 2 : 1;
    } else if (pairs[c]) {
      stack.push(pairs[c]);
    } else if (c === stack[stack.length - 1]) {
      stack.pop();
      if (stack.length === 0) return i;
    }
  }
  return -1;
}

/** Splits at top level (outside brackets and strings) on the separator character. */
function split(text, separator) {
  const parts = [];
  let depth = 0;
  let start = 0;
  for (let i = 0; i < text.length; i += 1) {
    const c = text[i];
    if (c === '"' || c === "'" || c === '`') {
      i += 1;
      while (i < text.length && text[i] !== c) i += text[i] === '\\' ? 2 : 1;
    } else if ('([{'.includes(c)) {
      depth += 1;
    } else if (')]}'.includes(c)) {
      depth -= 1;
    } else if (c === separator && depth === 0) {
      parts.push(text.slice(start, i));
      start = i + 1;
    }
  }
  parts.push(text.slice(start));
  return parts;
}

/** The expression that starts at `from`, up to a top-level `;`, `,` or closing bracket. */
function expressionAt(code, from) {
  let depth = 0;
  for (let i = from; i < code.length; i += 1) {
    const c = code[i];
    if (c === '"' || c === "'" || c === '`') {
      i += 1;
      while (i < code.length && code[i] !== c) i += code[i] === '\\' ? 2 : 1;
    } else if ('([{'.includes(c)) {
      depth += 1;
    } else if (')]}'.includes(c)) {
      if (depth === 0) return code.slice(from, i);
      depth -= 1;
    } else if ((c === ';' || c === ',') && depth === 0) {
      return code.slice(from, i);
    }
  }
  return code.slice(from);
}

function userParameters(text) {
  const names = new Set();
  for (const m of text.matchAll(/tarteaucitron\.user\.([A-Za-z0-9_]+)/g)) names.add(m[1]);
  for (const m of text.matchAll(/tarteaucitron\.user\[\s*["']([A-Za-z0-9_]+)["']\s*\]/g)) names.add(m[1]);
  return names;
}

/** The same text with the inside of every string literal blanked, so that identifiers are only searched in the code. */
function mask(text) {
  let out = '';
  for (let i = 0; i < text.length; i += 1) {
    const c = text[i];
    out += c;
    if (c === '"' || c === "'" || c === '`') {
      i += 1;
      while (i < text.length && text[i] !== c) {
        if (text[i] === '\\') { out += ' '; i += 1; }
        out += ' ';
        i += 1;
      }
      if (i < text.length) out += c;
    }
  }
  return out;
}

function staticExecutable(fragment) {
  const code = stripComments(fragment);
  const assignments = new Map();
  for (const m of mask(code).matchAll(/(?:^|[^.\w$])(?:(?:var|let|const)\s+)?([A-Za-z_$][\w$]*)\s*(\+?)=(?![=>])\s*/g)) {
    if (!assignments.has(m[1])) assignments.set(m[1], []);
    assignments.get(m[1]).push({ right: expressionAt(code, m.index + m[0].length), append: m[2] === '+' });
  }
  // A name assigned from several places (a variable reassigned, a function parameter of the same name): all of them count.
  const resolve = (text, depth = 0, seen = new Set()) => {
    const names = userParameters(text);
    if (depth >= 4) return names;
    for (const m of mask(text).matchAll(/(^|[^.\w$])([A-Za-z_$][\w$]*)/g)) {
      const identifier = m[2];
      if (seen.has(identifier) || !assignments.has(identifier)) continue;
      seen.add(identifier);
      for (const { right } of assignments.get(identifier)) for (const name of resolve(right, depth + 1, seen)) names.add(name);
    }
    return names;
  };
  /** The literal a variable starts with when it is only ever appended to afterwards, null otherwise. */
  const literalStart = (identifier) => {
    const list = assignments.get(identifier);
    if (!list || list[0].append || !isQuoted(unwrap(list[0].right)) || !list.slice(1).every((a) => a.append)) return null;
    return unwrap(list[0].right).slice(1, -1);
  };
  const found = new Map();
  const note = (names, reason) => {
    for (const name of names) {
      if (!found.has(name)) found.set(name, new Set());
      found.get(name).add(reason);
    }
  };

  const address = (expression, reason) => {
    let literal = '';
    for (const piece of split(unwrap(expression), '+')) {
      const text = unwrap(piece);
      const start = /^[A-Za-z_$][\w$]*$/.test(text) ? literalStart(text) : null;
      if (isQuoted(text) || start !== null) {
        literal += start ?? text.slice(1, -1);
        if (HOST_DONE.test(literal)) return;
        if (start !== null) for (const { right, append } of assignments.get(text)) if (append) note(resolve(right), reason);
      } else {
        note(resolve(text), reason);
      }
    }
  };

  for (const m of code.matchAll(/addScript\s*\(/g)) {
    const open = m.index + m[0].length - 1;
    const end = closing(code, open);
    if (end === -1) continue;
    const args = split(code.slice(open + 1, end), ',');
    address(args[0] ?? '', 'addScript address (source)');
    if (/^\s*['"]on/i.test(args[4] ?? '')) note(resolve(args[5] ?? ''), 'addScript handler (source)');
  }
  for (const m of code.matchAll(/\.src\s*=(?!=)\s*/g)) address(expressionAt(code, m.index + m[0].length), 'src assignment (source)');
  for (const m of code.matchAll(/setAttribute\s*\(\s*["']src["']\s*,\s*/g)) address(expressionAt(code, m.index + m[0].length), 'src attribute (source)');
  for (const m of code.matchAll(/\.(?:text|innerHTML|outerHTML)\s*(?:=(?!=)|\+=)\s*/g)) note(resolve(expressionAt(code, m.index + m[0].length)), 'inline code or markup (source)');
  for (const m of code.matchAll(/insertAdjacentHTML\s*\(\s*[^,]+,\s*/g)) note(resolve(expressionAt(code, m.index + m[0].length)), 'markup (source)');
  return found;
}

/**
 * @param {string} fragment catalogue fragment (the upstream source of one service)
 * @param {string} key
 * @param {string[]} params names read from `tarteaucitron.user`
 * @param {{ reads?: Array<'dynamic'|'static'> }} [options] which reads run (both by default; one at a time to test it)
 * @returns {{ executable: Set<string>, evidence: Map<string, string[]>, errors: string[] }}
 */
export function deriveExecutable(fragment, key, params, { reads = ['dynamic', 'static'] } = {}) {
  const markers = params.map((name, i) => [name, i + 1]);
  const evidence = new Map();
  const errors = [];
  const merge = (found) => {
    for (const [name, reasons] of found) {
      if (!evidence.has(name)) evidence.set(name, new Set());
      for (const reason of reasons) evidence.get(name).add(reason);
    }
  };
  if (markers.length > 0) {
    if (reads.includes('dynamic')) {
      for (const soft of [true, false]) {
        for (const hostile of [false, true]) {
          for (const array of [false, true]) {
            const probe = runProbe(fragment, key, markers, { soft, hostile, array });
            merge(probe.found);
            errors.push(...probe.errors);
          }
        }
      }
    }
    if (reads.includes('static')) merge(staticExecutable(fragment));
  }
  const names = new Set(params);
  return {
    executable: new Set([...evidence.keys()].filter((name) => names.has(name))),
    evidence: new Map([...evidence].filter(([name]) => names.has(name)).map(([name, reasons]) => [name, [...reasons]])),
    errors: [...new Set(errors)],
  };
}
