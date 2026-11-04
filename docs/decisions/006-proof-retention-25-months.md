# 006. Retention of consent proofs: 25 months in the France preset

Status: accepted, low risk accepted.

## Context

- The ability to prove consent comes from GDPR article 7(1). The storage limitation principle of article 5(1)(e) asks for a justified and announced retention period. The text of the regulation could not be fetched for this document (EUR-Lex returned an empty page on 2026-11-04), so these two articles are cited from the regulation itself and not from a page read.
- No source read for this project gives a duration for the proof of consent. The 25 months of the France preset reuse the maximum that the CNIL recommends for audience measurement data (<https://www.cnil.fr/fr/cookies-solutions-pour-les-outils-de-mesure-daudience>, read on 2026-11-04). That is another purpose, which makes the figure a choice of the module and not a rule.
- Time marks: the oldest consent that can still be used is 6 months old (renewal), and the choice cookie lives 13 months at most. At 25 months, about 12 months of proof remain after the last choice has expired.
- The module default without a preset is 60 months, which is also the cap ([decision 002](002-european-scope-and-retention-cap.md)). The two values must not be confused in the documentation.

## Decision

- The `fr-FR` preset sets `registry.retention_months` to 25 (`Resources/presets/fr-FR.json`).
- Reasoning: the maximum validity of the last choice (13 months) plus about one year for an inspection or a dispute.
- The administrator guide states the real default (60 months without a preset, also the cap), the preset value (25 months), and the need to choose, announce and purge. The cookie policy page template uses the duration of the published version.
- A site attached to the preset that picks a longer duration triggers the preset deviation alert (red, non-blocking).

## Consequences

- 25 months is not a legal requirement. The administrator can change it.
- The purge has to run (`ccm:registry:purge`, to be scheduled). A duration that is announced and never enforced is a separate defect, which a red alert reports when entries exceed the duration.
- Nothing is established about the limitation period that applies to claims. A lawyer should confirm it before it is quoted anywhere.

## Alternatives considered

- 60 months (the module default): far above what the proof of a choice valid for 13 months needs.
- 13 months: the proof would vanish with the expiry of the last choice, when an inspection could ask for it.
- 5 years (civil limitation period): no verified source, and storage limitation works against it.
