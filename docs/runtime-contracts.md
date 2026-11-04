# Runtime contracts

This page is for developers who read or write the data that the pieces of the module exchange: the inline script of the page head, the JavaScript engine, the registry endpoint, the Twig helpers and the back-office.
With it you can decode the consent cookie, call the registry endpoint, read the published configuration, and know in what order the served script is built.

A change that breaks one of these formats increments `format`. The engine and the server treat an unknown format as "no choice".

## Consent cookie

Name `ccm_consent`. The value is compact JSON encoded in base64url without padding, at most 3,500 bytes once encoded (`ConsentCookie::MAX_ENCODED_BYTES`).
The server sets it with `Path=/; Max-Age=34214400; SameSite=Lax`, plus `Secure` over HTTPS. It is never `HttpOnly`, and it has no `Domain` attribute. `Max-Age` is the 13-month ceiling (396 days).
The renewal delay is computed from the date of the last choice stored in the cookie, never from the cookie expiry.

Decoded:

```json
{
  "f": 1,
  "id": "4f1c2b9e-8d7a-4c3e-9b1f-2a6d5e7c8b90",
  "ver": 12,
  "t": 1761955200,
  "p": { "analytics": 1, "ads": 0, "social": 0, "media": 2, "support": 0, "personalization": 0 },
  "s": { "gtag": 1, "googletagmanager": 1, "facebookpixel": 0, "youtube": 1, "vimeo": 0, "crisp": 0 },
  "x": { "matomo": 1 },
  "pd": 0
}
```

| Key | Meaning |
|-----|---------|
| `f` | format, 1 |
| `id` | consent identifier (UUID v4). The server generates it. If the registry call fails, the script generates one, and the server keeps it when the choice is sent again, provided it is a valid UUID v4 |
| `ver` | number of the published version at the time of the choice |
| `t` | Unix time of the last choice, in seconds |
| `p` | state of each displayed purpose: `1` fully granted, `0` refused, `2` partial (at least one service granted, not the whole purpose). `essential` never appears |
| `s` | explicit state of each active service: `1` granted, `0` refused. A missing service is refused |
| `x` | exempt measurement: `1` active, `0` the visitor objected |
| `pd` | `1` while the choice written by the script has not been confirmed by the registry; the engine posts it again on the next page load |
| `pa`, `ps` | action and source of the choice that waits to be posted. Present only while `pd` is `1` |

Resolution rules:

- `hasConsent(service)` is `s[service] === 1`. `hasPurposeConsent(purpose)` is `p[purpose] === 1`: a partial purpose is not granted.
- The banner is shown again when an active published service is missing from `s` and its purpose is `1`. A purpose at `0` or `2` never triggers it.
- "Accept all" sets every purpose and active service to `1`. "Reject all" and "continue without accepting" set everything to `0`.
- A choice is stale when `t` plus the renewal delay has passed, when `t` is missing or not a whole number, or when `ver` is lower than the last substantial version. Until the visitor answers again, no non-exempt service runs and no Consent Mode `granted` update is sent (see [decision 005](decisions/005-stale-consent-suspension.md)).
- In live mode the script writes a new choice at once with `pd: 1`, then posts it. The cookie that the server sets in its answer replaces it. The script writes the cookie only for a new choice.
- In preview mode the cookie is named `ccm_preview` and nothing is posted.

## Registry endpoint

`POST /ccm/consent`, stateless, `Content-Type: application/json`, body at most 8 KiB (`ConsentController::MAX_BODY_BYTES`).

```json
{
  "format": 1,
  "consent_id": null,
  "action": "custom",
  "source": "preferences",
  "config_version": 12,
  "served_hash": "9b2e4f0c1a7d",
  "locale": "fr_FR",
  "path": "/fr/creme-hydratante.html",
  "purposes": { "analytics": 1, "ads": 0, "social": 0, "media": 2, "support": 0, "personalization": 0 },
  "services": { "gtag": 1, "googletagmanager": 1, "facebookpixel": 0, "youtube": 1, "vimeo": 0, "crisp": 0 },
  "exempt": { "matomo": 1 }
}
```

| Field | Rule |
|-------|------|
| `format` | `1` |
| `consent_id` | `null` for a first choice, otherwise the UUID v4 of the cookie |
| `action` | `accept_all`, `reject_all`, `custom`, `service_allow`, `withdraw`, `close_reject`, `exempt_optout`, `renew` |
| `source` | `banner`, `preferences`, `placeholder`, `api` |
| `config_version` | must be a published version, otherwise 422 |
| `served_hash` | 12 hexadecimal characters read from the inline configuration |
| `locale` | two letters, or two letters, underscore and two capitals (`fr`, `fr_FR`) |
| `path` | starts with `/`, no query string or fragment, at most 255 characters. Segments that carry secrets (`order/track/*`, opaque tokens) are masked before storage |
| `purposes`, `services` | required. Keys known to `config_version`, values `0` or `1` (`2` for purposes only). An unknown key gives 422 |
| `exempt` | optional. Same rule, keys are the exempt measurement services of `config_version`, which are not listed in `services` |

| Status | Body | Effect |
|--------|------|--------|
| 200 | `{"consent_id": "...", "recorded_at": "2026-10-31T21:20:00Z"}` | `Set-Cookie: ccm_consent=...` |
| 400 | `{"error": "invalid_json"}` | nothing stored. The body is not a JSON object, or the content type is not JSON |
| 403 | `{"error": "forbidden_origin"}` | `Origin` is not the current host, or `Sec-Fetch-Site` is neither `same-origin` nor `none`, or neither header is sent |
| 413 | `{"error": "too_large"}` | nothing stored |
| 422 | `{"error": "invalid_payload", "field": "services.foo"}` | nothing stored |
| 429 | `{"error": "rate_limited"}` and `Retry-After` | nothing stored |

- In preview and audit modes the engine never calls the endpoint.
- After a network failure, a 429 or a 5xx, the pending cookie (`pd: 1`) stays and the engine sends the choice again on a later page load. The wait before the next attempt is 60 seconds, doubles after each failure, and the engine gives up after the 8th failure. Any other 4xx drops the pending choice at once.
- The rate limit is 120 requests per minute per client address (sliding window, `ConsentRateLimiter::LIMIT`). The address comes from `Request::getClientIp()`. Behind a reverse proxy or a CDN, declare the proxy in `framework.trusted_proxies`, otherwise every visitor shares one counter. IPv6 addresses are grouped by /64.
- The registry proves what the browser sent. `consent_id` is a UUID that the visitor supplies or the server generates, so the module cannot authenticate an anonymous visitor.

## Published configuration

Rendered by the `layout.head.top` hook in `<script type="application/json" id="ccm-config">`. It is the same for all visitors of one language.

```json
{
  "format": 1,
  "version": 12,
  "hash": "9b2e4f0c1a7d",
  "substantialVersion": 10,
  "locale": "fr_FR",
  "mode": "live",
  "endpoint": "/ccm/consent",
  "script": "/ccm/12-9b2e4f0c1a7d.js",
  "preferencesUrl": "/ccm/preferences",
  "repromptDays": 182,
  "options": { "layout": "bar", "closeButton": false, "floatingButton": false, "reloadOnWithdraw": true, "blockEmbeds": true, "experimental": false },
  "gcm": { "enabled": true, "mode": "basic", "defaults": [], "waitForUpdate": 0, "adsDataRedaction": false, "urlPassthrough": false, "uet": false },
  "purposes": [ { "code": "analytics", "signals": ["analytics_storage"] } ],
  "services": [ { "key": "gtag", "purpose": "analytics", "kind": "script", "googleTag": true, "user": { "gtagUa": "G-ABC123XYZ" }, "cookies": ["_ga", "_ga_*"], "storage": [] } ],
  "exempt": [ { "key": "matomo", "user": {}, "cookies": ["_pk_id.*", "_pk_ses.*"] } ]
}
```

- `mode` is `live`, `preview` (the draft, `no-store`, nothing written to the registry) or `audit` (the choice cookie is not read, nothing is written).
- `repromptDays` is the renewal delay in days: months times 365 divided by 12, rounded down (6 months give 182).
- `endpoint`, `script` and `preferencesUrl` are root paths in the stored configuration. At render time the front replaces them with the URLs the router generates for the current request, so a shop installed in a sub-directory works. It adds `panel`, the address of the preferences panel (`/ccm/panel/<version>-<hash>-<locale>.html`), absent in preview mode.
- In preview mode `script` is `/ccm/preview-<token>.js` (`no-store`, served only with the proof cookie that the preview page sets).
- `gcm.defaults`: in basic mode one entry without region. Regions appear only in advanced mode, and the last entry is the fallback with an empty region list.
- `gcm.waitForUpdate` is the `wait_for_update` setting in advanced mode and `0` in basic mode.
- `services[].kind`: `script` (upstream fragment or URL), `media` (placeholder and `engage`), `custom` (administrator code), `declarative` (no script, the state is pushed to the `dataLayer`).
- `services[].cookies` hold names or `*` patterns, purged on withdrawal on the host and on each parent domain. `services[].storage` lists the `localStorage` and `sessionStorage` keys purged on withdrawal.
- `purposes` lists every displayed purpose, those without a Google signal included.
- The served hash is the first 12 hexadecimal characters of a SHA-256 over the published configuration, the module version, the served script and the resolved templates. The registry entry stores the hash that was served. Same configuration, same hash.

## dataLayer events

| Event | When | Content |
|-------|------|---------|
| `ccm_consent_ready` | every page load, after the `update` of the remembered choice (or without a choice) | `{event, ccm: {purposes, services, version}}` |
| `ccm_consent_update` | every action of the visitor | same |

## Preferences panel and stylesheet

The pages carry the banner, not the preferences panel.
`GET /ccm/panel/<version>-<hash>-<locale>.html` (stateless, `Cache-Control: public, max-age=31536000, immutable`) returns the markup of `preferences-panel.html.twig` for one published version and one active language of the shop.
The engine fetches it (`credentials: 'omit'`) the first time the visitor opens the panel and appends it to `<body>`.
A stale hash answers a 302 to the current address (`max-age=60`), and an unknown version or language a 404.
If the request fails, the engine sends the visitor to `preferencesUrl`.
The draft preview (no `panel` key) and the audit mode carry the panel inline, and the engine uses a panel already in the page without a request.

`GET /ccm/<hash>.css` (same cache headers) is the stylesheet, named after the hash of its own content. A stale hash answers a 302 to the current name.
The page links it from `<head>` (`<link rel="stylesheet" id="ccm-style">`), which blocks the first paint like inline CSS did.

## Served script

`GET /ccm/<version>-<hash>.js` (immutable cache, and a 302 to the current hash for a stale one). `ServedScriptAssembler` concatenates, in this order and without a bundler:

1. `assets/js/compat.min.js` (minified from `compat.js` by `npm run build`; same for `engine.min.js` and `head.min.js`, and a test fails when a committed file is stale): defines `window.CCMCompat.create(engineAdapter)`, which returns the `tarteaucitron` object that the vendored fragments are written against.
2. `assets/js/engine.min.js`: defines `window.CookieConsentManager`, the public API.
3. `CookieConsentManager.__boot();`: reads `#ccm-config`, sets `window.tarteaucitron`, `window.timeExpire` and `tarteaucitron.user` (the merged `user` values of the services).
4. The fragments of the active `script` and `media` services, verbatim from `Resources/catalogue/fragments/<key>.js`, then the administrator code of `custom` services wrapped in `tarteaucitron.services["<key>"] = {key, js: function () {...}, fallback: function () {}}`.
5. `CookieConsentManager.__start();`: runs `js()` of the granted services and `fallback()` of the refused `media` services, then drains `window.ccmQueue`.

The adapter passed to `CCMCompat.create`:

```js
{
  locale: "fr",                      // two letters, from config.locale
  user: { gtagUa: "G-ABC123XYZ" },   // merged user values of the active services
  lang: { allow: "Allow", fallback: "is disabled." }, // texts of the compatibility layer
  isAllowed(key) { return true; },
  placeholderHtml(key) { return "<div data-ccm-placeholder-box>...</div>"; },
  allow(key) { /* "Allow" click: action service_allow */ },
  log(message) {}
}
```

The compatibility layer is tested by execution: a test runs `js()` and `fallback()` of every exposed fragment against a layer wrapped in a `Proxy` that throws on any undeclared access. See [decision 001](decisions/001-service-catalogue.md).

## Registry CSV

The back-office export is in plain language (headers and values in the administrator's language). It follows the action and period filters of the Registry screen.
The technical export is the PHP service `CookieConsentManager\Registry\RegistryCsvExporter`, called without a `CsvLabeler`. No console command exposes it.
Columns, in order: `consent_id`, `recorded_at_utc`, `action`, `source`, `version`, `version_hash`, `locale`, `path`, `browser`, `purposes_json`, `services_json`, then `ip_truncated` when the option is on.

## Template attributes

The engine reads `data-ccm-*` attributes and never a CSS class. The attributes each template must keep are listed in the [integrator guide](integrator-guide.md#template-variables-and-required-attributes).
