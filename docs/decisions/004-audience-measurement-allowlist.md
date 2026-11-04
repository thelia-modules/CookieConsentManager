# 004. Allowlist of audience measurement tools that can be exempt from consent

Status: accepted.

## Context

The module can run an audience measurement service without consent when the administrator ticks five declarative conditions. At first only Google was excluded. A review found that a session recording tool and a tool that reuses the data for its own purposes could be exempted without any block, although both fall outside the exemption.

The CNIL page on audience measurement (<https://www.cnil.fr/fr/cookies-solutions-pour-les-outils-de-mesure-daudience>, read on 2026-11-04) names no product. It sets conditions: the tracker is limited to measuring the audience of the publisher's own site, it produces anonymous statistics only, and it does not cross the data with other processing. It also recommends limiting the lifetime of trackers (13 months is given as an example, not extended on each visit) and keeping the collected information for at most 25 months. These two durations are recommendations, not conditions. The historical list of named solutions could not be read again.

What decides it in the code: for an exempt service the engine runs `js()` of the fragment and never `fallback()` (`assets/js/engine.js`). A tool whose "exempt" mode lives in `fallback()`, or is not enabled, would collect in full mode.

## Decision

- Only four catalogue keys can be exempt (`Validation/ExemptionWhitelist.php`): `matomo` (self-hosted), `wysistathightrack`, `plausible` and `simpleanalytics`. Any other key is refused (`exempt_not_allowed`). Google stays refused (`exempt_google`), and so does a service outside audience measurement (`exempt_service`).
- A custom service or a service declared by a module is never exempt: the module cannot know what a free URL or a free piece of code loads.
- The basis is a case-by-case reading of the fragment that actually runs (`js()`), not a list of "evaluated" tools. The conditions on the tool side (anonymised IP, retention, no User ID, account mode) stay declarative: the checkboxes are kept and the allowlist comes on top.
- The back-office offers only the list, plus the services already exempt so that they can be removed, with a label and a help text for each missing condition.
- Variants with a forced consent mode or a tag manager (Matomo Cloud, Matomo Tag Manager, Piwik PRO), heatmap and profiling tools, Google tools and tools not reviewed are excluded.

## Consequences

- Adding a tool takes a module release, based on the vendor's documentation and a reading of its fragment.
- Plausible and Simple Analytics read the user agent and the screen size, so a residual risk remains. A publisher who wants none can remove them from the list.
- None of the four tools has been run against a real account. The review rests on reading the fragments. The Matomo and Wysistat exemption pages and the terms of several vendors were not read.
- The module cannot see the configuration on the vendor side. The site publisher remains responsible for the compliance of the tool.

## Alternatives considered

- No list, checkboxes only: they block nothing.
- The whole "audience measurement" purpose: it contains profiling tools and tools that reuse the data.
- Reusing the historical CNIL list: not possible, the source could not be read again.
