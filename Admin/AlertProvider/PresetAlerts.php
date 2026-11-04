<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin\AlertProvider;

use CookieConsentManager\Admin\ActiveLanguages;
use CookieConsentManager\Admin\Alert;
use CookieConsentManager\Admin\FieldLabeler;
use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Catalogue\CookieDurations;
use CookieConsentManager\Preset\PresetApplier;
use CookieConsentManager\Preset\PresetCatalogue;
use CookieConsentManager\Preset\PresetDeviation;

/** Presets attached to several languages, deviations from the preset of the main language, cookie lifetimes. */
final readonly class PresetAlerts implements AlertProvider
{
    public function __construct(
        private CatalogueReader $catalogue,
        private ActiveLanguages $languages,
        private FieldLabeler $labels,
        private PresetCatalogue $presets,
        private PresetApplier $applier,
        private PresetDeviation $deviation,
        private CookieDurations $durations,
        private AlertText $text,
    ) {
    }

    public static function priority(): int
    {
        return 600;
    }

    public function alerts(array $draft): array
    {
        $alerts = [];

        $this->presets($draft, $alerts);
        $this->presetDeviations($draft, $alerts);
        $this->durations($draft, $alerts);

        return $alerts;
    }

    /**
     * @param array<string, mixed> $draft
     * @param list<Alert>          $alerts
     */
    private function presets(array $draft, array &$alerts): void
    {
        $attached = [];
        foreach ($draft['presets'] as $code) {
            if (null !== $preset = $this->presets->find($code)) {
                $attached[$code] = $preset;
            }
        }

        foreach ($this->applier->contradictions(array_values($attached)) as $contradiction) {
            $alerts[] = new Alert(
                Alert::WARNING,
                'presets_contradict_'.$contradiction['first'].'_'.$contradiction['second'],
                $this->text->t('The presets "%first%" and "%second%" attached to different languages disagree on settings shared by every language: only the preset of the main language is applied to them. Check these settings.', ['%first%' => $contradiction['first'], '%second%' => $contradiction['second']]),
                'ccm_admin_presets',
                array_map(fn (string $path): string => $this->labels->label($path, $this->text->locale(), $draft), $contradiction['paths']),
            );
        }
    }

    /**
     * The preset of the main language is the reference: a setting shared by every language that protects visitors
     * less than it is a red alert naming the setting and the value of the preset. Nothing is blocked.
     *
     * @param array<string, mixed> $draft
     * @param list<Alert>          $alerts
     */
    private function presetDeviations(array $draft, array &$alerts): void
    {
        $code = $draft['presets'][$this->languages->mainCode()] ?? null;
        if (null === $code || null === $preset = $this->presets->find($code)) {
            return;
        }

        foreach ($this->deviation->between($draft, $preset) as $deviation) {
            $alerts[] = new Alert(
                Alert::DANGER,
                'preset_deviation_'.str_replace('.', '_', $deviation['path']),
                $this->text->t('The setting "%field%" is %value% while the preset "%preset%" sets %reference%: visitors are protected less than the preset intends.', [
                    '%field%' => $this->labels->label($deviation['path'], $this->text->locale(), $draft),
                    '%value%' => $this->shown($deviation['current']),
                    '%preset%' => $preset->label,
                    '%reference%' => $this->shown($deviation['reference']),
                ]),
                str_starts_with($deviation['path'], 'consent_mode') ? 'ccm_admin_consent_mode' : ('registry.' === substr($deviation['path'], 0, 9) ? 'ccm_admin_registry' : 'ccm_admin_dashboard'),
            );
        }
    }

    private function shown(mixed $value): string
    {
        return \is_bool($value) ? $this->text->t($value ? 'Yes' : 'No') : (string) $value;
    }

    /**
     * Under a preset attached to another language than the main one, an active service whose cookies have no known
     * lifetime leaves the visitor without the duration (RGPD art. 13). On the main language the validator refuses it.
     *
     * @param array<string, mixed> $draft
     * @param list<Alert>          $alerts
     */
    private function durations(array $draft, array &$alerts): void
    {
        if ([] === $draft['presets'] || isset($draft['presets'][$this->languages->mainCode()])) {
            return;
        }

        $without = array_map(fn (string $key): string => $this->labels->label('services.'.$key, $this->text->locale(), $draft), array_keys($this->durations->withoutLifetime($draft['services'], $this->catalogue)));

        if ([] !== $without) {
            $alerts[] = new Alert(Alert::DANGER, 'cookie_duration_missing', $this->text->t('No cookie lifetime is known for: %services%. Visitors are not told how long these cookies last: enter the durations of the publisher on the service.', ['%services%' => implode(', ', $without)]), 'ccm_admin_services');
        }
    }
}
