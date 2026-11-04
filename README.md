# CookieConsentManager

This module helps you collect and prove consent. It does not make a website compliant by itself and is not legal advice.

CookieConsentManager is a cookie and tracker consent module for Thelia 3 (package `thelia/cookie-consent-manager-module`, module code `CookieConsentManager`).

- It shows a two-level banner (bar or modal) where refusing takes one click, a preferences panel, and a no-JavaScript preferences page at `/ccm/preferences`.
- It keeps a consent registry with a printable proof sheet, a CSV export and a retention purge. Published versions are immutable, and nothing is served until an administrator publishes.
- It blocks third-party scripts and embedded content until consent, with a service catalogue derived from tarteaucitron.js (23 verified services, 213 experimental ones) and a way for other modules to declare theirs.
- It sends Google Consent Mode v2 signals (basic or advanced, `gtag` and Google Tag Manager).
- The banner, the panel and the back-office are translated into French, English, German, Dutch and Spanish. A France (CNIL) preset provides texts and settings.

It is not an IAB TCF or Google-certified CMP. MIT licence.

## Compatibility

PHP 8.3 or later. `composer.json` requires `thelia/core ^3.0`. The Flexy theme (hooks `layout.*`) is supported, and any other theme works through a neutral fallback.

| | 3.0 | 3.1 | 3.2 | 3.3 |
|---|-----|-----|-----|-----|
| Level of testing | smoke | smoke | smoke | full |
| Injection in `layout.head.top` and `layout.body.top` (Flexy) | yes | yes | yes | yes |
| "Manage my cookies" link | hook `layout.footer.top` | hook `layout.footer.top` | slot `footer_links` | slot `footer_links` |
| Shared cache purge on publication | not applicable | not applicable | not applicable | yes |
| Scheduled registry purge | cron and command | cron and command | cron and command | `scheduler_thelia` worker or cron |

"Smoke" means installation, activation, injection, banner and one recorded choice, checked on Thelia 3.0.0, 3.1.6 and 3.2.5 on 2026-10-31. Version-specific limits are noted in the docs.

## Installation

From the root of the Thelia site (with DDEV, prefix the commands with `ddev`):

```bash
composer require thelia/cookie-consent-manager-module
php Thelia module:refresh
php Thelia module:activate CookieConsentManager
php Thelia cache:clear
```

- Composer places the module in the folder defined by the `installer-paths` of the Thelia skeleton (type `thelia-module`). Until the package is published on Packagist, declare the Git repository or a `path` repository in `composer.json`.
- `module:refresh` runs `install()`: it creates the five `ccm_*` tables with `CREATE TABLE IF NOT EXISTS` and never drops anything.
- On a standard console `module:activate` can be silent (seen on 3.0). Check with `php Thelia list ccm`, which shows `ccm:config:export`, `ccm:config:import`, `ccm:config:publish`, `ccm:registry:purge`, `ccm:services:sync` and `ccm:stats:rebuild`. The back-office shows "Cookie consent" in the top menu. You can also activate the module in Configuration, Modules.
- After activation an empty draft exists. Nothing is injected until a version is published, apart from the draft preview.

First steps: back-office, "Cookie consent", setup wizard, preview, then publish. See the [administrator guide](docs/administrator-guide.md).

## Connecting the module to a theme

With the Flexy theme the banner is injected through the hooks `layout.head.top` and `layout.body.top`. With another theme, call `theme_hook('layout.head.top')` and `theme_hook('layout.body.top')` in its `base.html.twig`. Templates can be overridden one by one; see the [integrator guide](docs/integrator-guide.md).

Once the module is disabled, the Twig functions `ccm_has_consent`, `ccm_has_purpose_consent`, `ccm_placeholder` and `ccm_open_link` no longer exist, and a template that calls them fails on the whole site. Wrap such calls in the guard described in the [integrator guide](docs/integrator-guide.md#disabling-the-module-and-the-twig-guard).

## Moving a hard-coded Google Tag Manager

1. Disable the Thelia `GoogleTagManager` module if it is active (the service of this module refuses to activate next to it).
2. Remove the GTM `<script>` snippet and its `<noscript><iframe>` from the theme. The `noscript` loads GTM without consent.
3. In the back-office, "Purposes and services", enable "Google Tag Manager" and enter the container ID.
4. Keep Consent Mode on "Basic", and use the triggers `ccm_consent_ready` and `ccm_consent_update` in GTM.
5. Publish, then run the Verification before and after "Reject all".

The full procedure is in the [integrator guide](docs/integrator-guide.md#moving-a-hard-coded-gtm).

## Updating

```bash
composer update thelia/cookie-consent-manager-module
php Thelia module:refresh
php Thelia cache:clear
```

- `module:refresh` runs `update()`: the schema is applied again (idempotent), then any update scripts shipped in `Config/update/`, and the services declared by other modules are synchronised.
- The bundled service catalogue follows the module version (tarteaucitron.js v1.37.0 today). There is no catalogue update on its own.
- A publication is needed after most updates. Visitors are served a versioned script `/ccm/<version>-<hash>.js`, and the hash covers the engine, the compatibility layer, the fragments and the templates. When the served hash differs from the published version, the dashboard shows a red alert "The served rendering (...) differs from version ...". Publish again from the back-office, or from the console:

  ```bash
  php Thelia ccm:config:publish --comment="Module update"
  ```

  The command refuses to publish (exit code 1) when nothing changed since the last version; after an update the served hash has changed, so it goes through. Add `--force` to publish anyway. `--substantial` shows the banner again to everyone, which a module update does not need.
- `php Thelia ccm:stats:rebuild` recomputes the first-choice statistics from the registry, which is never modified. Run it if the dashboard figures look wrong or empty, for example after restoring the registry, and after an update to recount the days still covered.
- Published versions are immutable: a module update never changes what a visitor consented to.

## Deploying a configuration from the console

```bash
php Thelia ccm:config:export --output=ccm-config.json       # exports the DRAFT, not the published version
php Thelia ccm:config:import ccm-config.json                 # creates a draft, never publishes
php Thelia ccm:config:publish --confirm-environment --comment="Go live"
```

- Environment fields (service identifiers, policy address, regions) come from another site. The import lists them, and you must confirm or re-enter them before publishing. Without `--confirm-environment`, publication is refused.
- `ccm:config:import` exits with `0` when the draft is created. Fields to confirm are printed as warnings, which is not a failure. Any other code means the file was unreadable or refused. Only invalid addresses block the write; other errors are imported so that you can correct them in the back-office.
- `ccm:config:publish` options: `--confirm-environment`, `--substantial`, `--force`, `--comment=<text>`.
- The file schema is `Config/schema/ccm-config-1.schema.json`. The key `registry.link_customer` of older exports is ignored on import.
- Registry purge: `php Thelia ccm:registry:purge` (`--dry-run` only counts). Schedule it, for example `17 3 * * *`. On Thelia 3.3 with a worker on `scheduler_thelia`, a daily task at 03:17 already exists.

## Disabling and removing

- `php Thelia module:deactivate CookieConsentManager` stops every injection (no banner, no script, no Consent Mode). Data is kept and the registry is intact after reactivation.
- Deleting the module from the back-office with the option to delete the module data drops the five tables `ccm_consent_entry`, `ccm_daily_stat`, `ccm_draft_setting`, `ccm_service_config` and `ccm_version`, and the `ccm_last_purge_at` setting. `composer remove` on its own leaves the tables in place.

## Limits

- Not an IAB TCF 2.2 or Google-certified CMP. Not covered: server-side geolocation, cross-domain consent, CSP nonce, Microsoft Clarity consent signal.
- The module cannot authenticate an anonymous visitor: the registry proves what the browser sent, not who sent it.
- The right to publish (CREATE on the module in Configuration, Administrator profiles) lets a user serve JavaScript to all visitors, through custom services. Grant it with care.
- Behind a reverse proxy or a CDN, declare the proxy in `framework.trusted_proxies`. The registry endpoint is rate limited to 120 requests per minute per client address, and without it all visitors share one counter.
- The Flexy theme loads Google fonts before the `layout.head.top` hook. The module cannot prevent it; host the fonts on the site. The dashboard and the Verification report it.
- The Thelia 3.3 shared cache serves only visitors without a cookie, so a page that calls `ccm_has_consent` must stay out of it.
- On a shop served by several servers with a local application cache each, a publication can take up to 300 seconds to reach all of them.
- Cookie durations without an official source (Meta Pixel, for example) are not provided; the administrator enters them. The 213 imported services are experimental.
- Third-party cookies set on a third-party domain cannot be erased by the module. The Verification does not see `HttpOnly` cookies or requests inside third-party iframes.
- The inline script and `<style>` of the page head carry no CSP nonce. The engine does not need `unsafe-eval`.
- None of the four audience measurement tools that can be exempt from consent has been tested on a real account.

## Documentation

- [Administrator guide](docs/administrator-guide.md): merchants and project managers (a French version is in `docs/fr/`).
- [Integrator guide](docs/integrator-guide.md): theme, template overrides, JavaScript and Twig APIs, other modules, CSP, tests.
- [Runtime contracts](docs/runtime-contracts.md): consent cookie, registry endpoint, published configuration, served script.
- [Maintaining the service catalogue](docs/maintaining-the-catalogue.md): updating from a new tarteaucitron.js release.
- [Decisions](docs/decisions/): why the service catalogue, the Consent Mode guardrail, presets, the exemption allowlist, stale consent and proof retention work the way they do.
- [CHANGELOG](CHANGELOG.md).
- `Resources/catalogue/REPORT.md`: origin and content of the service catalogue.

## Licence

MIT (`composer.json`). The service catalogue and the fragments in `Resources/catalogue/` derive from tarteaucitron.js v1.37.0, Copyright (c) 2014 AmauriC, MIT licence. The notice is kept in `Resources/vendor/tarteaucitron/LICENSE` and in the header of the vendored file.
