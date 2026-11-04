# 003. Presets per language with a shared base

Status: accepted.

## Context

- The legal framework depends on the country. France, for example, expects refusing to be as simple as accepting and the choice to be asked again after about 6 months. A multilingual shop serves several countries with one shop.
- The settings that matter legally (renewal delay, reload on withdrawal, blocking of embedded content, Consent Mode, registry retention, IP address) apply to the whole shop. They do not exist per language.
- Texts, labels and purpose descriptions do exist per language.
- A preset applied once locks nothing: less protective settings remained possible with no signal at all.

## Decision

- A preset is a file `Resources/presets/<language>-<COUNTRY>.json` in a partial `ccm-config/1` format. It cannot carry services, colours or addresses; reading such a file fails (`Preset/Preset.php`, `fromDocument`).
- Import is done for a target language (`Preset/PresetApplier.php`). Texts, purpose labels and the attachment (`presets[language]`) go to the target language. The shared base (`general`, `consent_mode`, `registry`, `exempt_measurement`, `policy.page_title`) is written only when the target language is the main language of the shop (`Admin/ActiveLanguages::mainCode()`).
- The attachment is a field of the draft, of the export schema and of the published version (`presets`).
- The preset attached to the main language is the reference. A shared setting that is less protective (eight settings, `Preset/PresetDeviation.php`) raises a red alert that names the setting and the preset value, without blocking. Two attached presets that contradict each other on a shared setting raise an orange alert.
- Under the `fr-FR` preset, the cookie policy address of the `fr` language is required and must not be a terms-and-conditions page. Under any attached preset, a responsible entity is required for each active catalogue service (publication is refused otherwise).

## Consequences

- Importing the French preset on `fr` while English is the main language applies the texts only. The French base is not applied, and the preview says so.
- A setting changed after the import stays "attached". The attachment does not measure drift; the alerts do.
- The France rules are hard-coded on the `fr-FR` code (`DraftValidator::FRANCE_PRESET`). A second preset with its own rules would require moving those rules into the preset file.
- Detecting a terms-and-conditions page relies on the words of the address. A page named differently passes.
- `policy.page_title` is unique and follows the main language.

## Alternatives considered

- One preset per shop: it forces a single country on multilingual shops.
- Writing the shared base whatever the target language: importing a preset for a secondary language would overwrite the settings of the main one.
- Locking the preset settings: the merchant keeps control, so alerts replace locks.
