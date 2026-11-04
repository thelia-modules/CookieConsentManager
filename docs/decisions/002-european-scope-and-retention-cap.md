# 002. European scope of the Consent Mode guardrail and a 60-month retention cap

Status: accepted.

## Context

- In advanced mode the administrator sets the default of each Consent Mode signal per region. A `granted` default for the European Union amounts to storing a tracker without consent: in a test the `_ga` cookies were set, a hit with `gcs=G111` was sent, and the banner was still displayed.
- The registry retention field accepted any duration (600 months was published). No source read for this project gives a maximum duration for proof of consent.
- Choice made: block what can be detected without ambiguity, and flag editorial matters with a non-blocking alert.

## Decision

- A `granted` default is refused (error `default_granted`, `Validation/DraftValidator.php`) for every signal except `security_storage` when the region covers Europe. A region covers Europe when its ISO code (the part before the hyphen: `FR-75` counts as `FR`) is in `EUROPEAN_REGIONS`: the 27 Member States, IS, LI, NO, GB, CH, and the codes `EU` and `EEA`. A line without a region, which is the worldwide default, counts as European. A `granted` default for a region outside the list (for example `US-CA`) is accepted.
- `registry.retention_months` is between 1 and 60 (`MAX_RETENTION_MONTHS`, error `retention_max`, `maximum: 60` in the import schema). 60 months is the module default and is reused as the cap for lack of a better reference.
- Advanced mode stays available behind a confirmation checkbox and a permanent red alert.

## Consequences

- A configuration import that carries a European `granted` default or a retention above 60 months is refused on import, on save and on publication.
- The region list is a constant of the code: adding a European country is a module change. It does not cover non-European subdivisions that share a European two-letter code (assumption: a two-letter ISO code identifies the country).
- The cap does not say that 60 months is a good duration. It is a bound. The module default without a preset equals the cap; the France preset sets 25 months ([decision 006](006-proof-retention-25-months.md)).
- Only the module's own draft is checked. A Consent Mode `update` sent by other code is not.

## Alternatives considered

- A red alert alone: a publication would still set trackers before consent.
- Forbidding every `granted` default: a `granted` default outside Europe is a legitimate choice for a merchant.
- A 25-month cap (the CNIL recommendation for audience measurement data): that is another purpose, and the proof duration is set by the preset.
