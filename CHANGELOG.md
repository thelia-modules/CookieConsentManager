# Changelog

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Versions follow [Semantic Versioning](https://semver.org/).

## [1.0.2] - 2026-11-04

### Fixed

- A consent cookie whose time is missing or not a whole number is now treated as expired, in the head script, the engine and the PHP helpers. The engine used to keep such a choice forever, so the banner never came back after the renewal delay.

## [1.0.1] - 2026-11-04

### Security

- A service flagged as coming from a module must be declared by an active module with the `ccm.service` tag: an unknown key, code on a declared service, a script address other than the declared one, or a "strictly necessary" status the declaration does not give is a validation error. The import refuses such a file and publication is blocked.
- Everything that can put a script on the storefront now needs the right that publishes (CREATE): writing or changing the code or script address of a custom service, importing a configuration, restoring a version and switching on experimental services. The buttons and fields are greyed out or hidden for other profiles.
- The registry CSV export and the proof sheet need UPDATE instead of VIEW; the list stays readable with VIEW.
- The publication diff shows the full code and script address of an added custom service.
- The configuration import refuses a file over 1 MB and an invalid upload with a message instead of an error page.
- The consent registry no longer keeps the password reset token of the page address: `password/reset` is a secret route and any segment holding 24 or more hexadecimal characters is masked.
- The engine restores an iframe only from an `https://` or protocol-relative (`//host`) address and copies only plain attributes from `data-ccm-attr-*` (never an event handler or `srcdoc`), on the iframe and on a released placeholder.
- A cookie policy address containing a backslash is refused, in the validation and in the engine.
- The version in the script and panel addresses is limited to 9 digits: a longer one gives a 404 instead of a 500.

### Added

- A warning when the fallback address of the cookie policy is a terms and conditions page that no active language uses yet: a language activated later would show it to its visitors.

### Fixed

- `symfony/rate-limiter` is declared in `composer.json` (used by the consent endpoint).

## [1.0.0] - 2026-11-04

First public release.

### Added

- Two-level banner (bar or modal) with reject as simple as accept, a preferences panel with a switch per purpose and per service, an optional floating button, a "Manage my cookies" link, and a no-JavaScript preferences page at `/ccm/preferences`.
- Consent registry with a proof sheet, filters, plain-language CSV export, retention purge (`ccm:registry:purge`) and first-choice statistics (`ccm:stats:rebuild`). Optional truncated IP address.
- Draft and immutable published versions, with a readable diff between versions, restore as draft, a "substantial change" option that shows the banner again to everyone, and a served hash that proves the rendering a visitor received.
- Google Consent Mode v2 in basic or advanced mode, `gtag` and Google Tag Manager support, and the `ccm_consent_ready` and `ccm_consent_update` dataLayer events.
- Service catalogue derived from tarteaucitron.js v1.37.0: 23 verified services and 213 experimental ones, custom services (script address, own code, or declaration only), and services declared by other modules with the `ccm.service` tag.
- Blocking of hard-coded scripts (`type="text/plain" data-ccm-service` or `data-ccm-purpose`) and automatic replacement by a placeholder of the iframes of 41 media services (YouTube, Vimeo, Dailymotion, Google Maps, Spotify, Google Docs and more), in pages and in LiveComponent answers.
- Per-service responsible entity (prefilled for 18 of the 23 verified services), cookie durations (catalogue values from official documentation, administrator entry for the others), joint responsibility for services where the publisher and the site are joint controllers.
- Five languages for the banner, the panel, the engine messages and the back-office: French, English, German, Dutch and Spanish.
- Presets screen and a France (CNIL) preset: import per language, before and after preview, attachment of a language to a preset, alerts when a setting is less protective than the preset.
- Cookie policy page template generated from the published version, in the five languages.
- Dashboard alerts (red, orange, blue) and back-office guardrails that refuse a Consent Mode `granted` default for a European region or a line without a region, an exemption outside the allowlist (self-hosted Matomo, Wysistat, Plausible, Simple Analytics), a changed purpose on a catalogue service, a retention above 60 months, empty labels, invalid addresses, and too-light colours.
- Verification screen that visits pages before any choice and after "Reject all", lists third-party domains and cookies, and produces a printable report.
- Configuration export and import in JSON (`ccm-config/1`) and console commands `ccm:config:export`, `ccm:config:import` and `ccm:config:publish`.
- Rights per administrator profile (VIEW, UPDATE, CREATE to publish, DELETE to purge) and a read-only mode.
- Template overrides in the theme with a contract marker (`{# ccm-templates:1 #}`), and a JavaScript API (`window.ccmQueue`, `window.CookieConsentManager`, `ccm:*` events) and Twig functions (`ccm_has_consent`, `ccm_has_purpose_consent`, `ccm_placeholder`, `ccm_open_link`).
- A choice that has expired or predates the last substantial version suspends every non-exempt service until the visitor answers again.
- Rate limiting of the registry endpoint, origin checks, and masking of secret segments in recorded page paths.
- Deletion of the module data when the module is removed with that option.

### Notes

- Smoke-tested on Thelia 3.0, 3.1 and 3.2, fully tested on 3.3. See the README for the differences.
- Not an IAB TCF or Google-certified CMP. The module helps collect and prove consent and is not legal advice.
