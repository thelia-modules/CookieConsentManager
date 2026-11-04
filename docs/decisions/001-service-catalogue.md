# 001. Service catalogue taken from tarteaucitron.js through a compatibility layer

Status: accepted.

## Context

The module needs a catalogue of third-party services (analytics, advertising, video, chat) with the cookies, domains and loading code of each. Writing and maintaining 240 of them by hand is out of reach for one module. [tarteaucitron.js](https://github.com/AmauriC/tarteaucitron.js) (MIT licence) publishes such a catalogue as `tarteaucitron.services.js`. The module keeps its own engine (banner, consent state, registry, Consent Mode v2) and reuses only that file.

Measured on v1.37.0 (282 KB):

- 249 root assignments of the form `tarteaucitron.services.<key> = {`. A grep on lowercase keys finds 248 and misses `intercomChat`.
- 356 `js` and `fallback` functions.
- 6 services whose `cookies` is an immediately invoked function that reads `tarteaucitron.user.*` at load time, so it is not static data.

The fragments depend on more than direct calls to the tarteaucitron core:

| Transitive dependency | Consequence |
|-----------------------|-------------|
| bare global `timeExpire` | `ReferenceError` in strict mode if it is missing |
| `tarteaucitron.services[id]` and `lang` entries read by `engage` | the placeholder needs them |
| `fixSelfXSS` (through `getElemAttr`), `parameters.useExternalJs` (through `addScript`) | |
| `addInternalScript`, `makeAsync.getAndParse` and `execJS` with `eval` and a hijacked `document.write` | needs `unsafe-eval` |
| writes into the registry (`services.matomo.cookies.push`) | |
| `this.js()` called from `fallback` | a refused service would run its script |
| upstream defaults `bingConsentMode`, `piwikConsentMode`, `pianoConsentMode` at `true`, `softConsentMode` at `false` | the Microsoft UET tag loads without consent |
| `engage` renders its own placeholder, unrelated to the registry | |

A static check for `tarteaucitron.x` references sees none of these and would pass with a broken gtag.

## Decision

1. The module vendors the upstream services file, pinned to a version (v1.37.0), with the MIT licence and copyright kept in its header (`Resources/vendor/tarteaucitron/`).
2. Importing the catalogue is a maintainer tool, not a runtime feature: a Node script in `tools/catalogue/` splits the file on root assignments, evaluates the metadata in a sandbox (with simulated `user.*` values for the dynamic cookies) and writes a JSON catalogue and one JS fragment per service. The output is committed. Updating the catalogue means releasing a module version; merchants have no import command. See [maintaining the catalogue](../maintaining-the-catalogue.md).
3. The engine exposes a compatibility layer, `window.tarteaucitron`, that covers the whole surface above:
   - globals: `timeExpire`;
   - objects: `services`, `lang`, `user`, `parameters`, `job`, `state`;
   - functions: `getElemAttr`, `addScript`, `addInternalScript`, `fallback`, `engage`, `getStyleSize`, `getElemWidth` and `getElemHeight`, `isAjax`, `getLocale`, `getLanguage`, `extend`, `cookie.purge`, `fixSelfXSS`.
4. The `parameters` handed to fragments are frozen: every `*ConsentMode` is `false`, `softConsentMode` is `true`, `useExternalJs` is `false`. The engine is the only source of `consent` commands.
5. `engage(id)` is redefined to render the module's own placeholder, so there is a single placeholder component.
6. Services that rely on `makeAsync`, `eval` or `document.write` are not supported. They are excluded and listed in the import report. The module needs no `unsafe-eval`.
7. A test runs `js()` and `fallback()` of each exposed service against the compatibility layer wrapped in a `Proxy` that throws on any undeclared access. No `fallback()` may run `js()`.
8. Dynamic cookie names are replaced by declared patterns (`_ga_*`) in the table of verified services.
9. The front receives only the fragments of the enabled services, from the versioned route `GET /ccm/<version>-<hash>.js`, never the whole file and never an asset written to `public/`.
10. Nothing else of tarteaucitron is reused: no core, no UI, no CSS, no texts.

## Consequences

- 236 of the 249 upstream services have a fragment (13 are excluded: 6 `gcm` entries, 6 that use `makeAsync`, and Universal Analytics). Only the verified ones (23 at the time of writing) are shown by default; the imported ones sit behind the "experimental services" setting.
- An upstream update means running the tool again, reviewing the diff, running the execution test, releasing a module version, and having each merchant publish again (the served hash changes).
- Fragments run reviewed third-party code. The diff is readable and the tool never runs on a shop.
- Fragments cannot read the Consent Mode configuration of the engine. In advanced mode, the engine calls `js()` of the Google tag services directly.
- Cost: the compatibility layer has to be maintained, and a few upstream services are left out.
- Attribution: the vendored file, the generated catalogue and the README carry the MIT notice.
