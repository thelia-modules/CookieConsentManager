# 005. Suspending an expired or outdated consent

Status: accepted.

## Context

- The `ccm_consent` cookie lives 13 months at most, but the renewal is computed from the date of the choice (`t`) and the configured delay (6 months by default). After that date, or after a "substantial" publication, the banner was shown again while the granted services kept running, because the engine's `on()` function ignored `needsBanner`.
- A consent whose validity has ended is no longer a consent.

## Decision

- A choice is stale when `t + repromptDays` has passed or when `ver` is lower than `substantialVersion` (`stale` in `assets/js/engine.js`, duplicated identically in `assets/js/head.js`, which runs before the engine).
- While the choice is stale: no non-exempt service runs (`on()`, `scriptAllowed`, `hasConsent`, `hasPurposeConsent`, the switches and "Allow" read a fresh choice, never the stale one); no `granted` Consent Mode update is sent and `ccm_consent_ready` carries no granted purpose; the banner is shown. A click on "Allow" does not bring the old grants back.
- Exempt measurement is not affected: its state is the objection.
- Adding a service to a purpose that was already granted does not make the choice stale. Only the banner is raised again (`needsBanner`) and the services already granted stay active.
- Related: on every choice of the visitor, the cookies of the refused services are purged, as on withdrawal, and the Consent Mode update covers every managed signal, defaults included, not only those of the purposes.

## Consequences

- A visitor who ignores the redisplayed banner loses the optional services. This is intended. Non-exempt audience measurement drops until the visitor answers.
- The expiry is read in the browser, on the visitor's clock. A wrong clock moves the date. The server does not decide.
- Measured in a browser: at 100 days (valid choice) granted services load; at 183 days (6-month renewal) no third-party host is contacted, only the Consent Mode `default` is sent and the banner shows. A substantial publication is covered in jsdom only, not on a published site.
- A page served from a shared cache stays correct, since the decision is taken in the browser.

## Alternatives considered

- Keeping the previous behaviour (banner again, services still active): it equals a tracker without a valid consent.
- Suspending on expiry only, not on a substantial version: a substantial version means that what was announced has changed.
- Making the choice stale when a service is added: every visitor would lose grants that remain valid for the services already present.
