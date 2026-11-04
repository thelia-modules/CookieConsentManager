<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin\AlertProvider;

use CookieConsentManager\Admin\Alert;
use CookieConsentManager\Admin\FieldLabeler;
use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Draft\ServiceControllers;
use CookieConsentManager\Validation\ConflictingModuleDetector;

/** Third-party emitters of the shop and the state of the enabled services. */
final readonly class ServiceAlerts implements AlertProvider
{
    public function __construct(
        private CatalogueReader $catalogue,
        private ConflictingModuleDetector $modules,
        private ServiceControllers $controllers,
        private FieldLabeler $labels,
        private AlertText $text,
    ) {
    }

    public static function priority(): int
    {
        return 500;
    }

    public function alerts(array $draft): array
    {
        $alerts = [];

        $this->emitters($alerts, $draft);
        $this->services($draft, $alerts);

        return $alerts;
    }

    /**
     * @param array<string, mixed> $draft
     * @param list<Alert>          $alerts
     */
    private function emitters(array &$alerts, array $draft): void
    {
        $videos = ['youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'dailymotion' => 'Dailymotion'];
        $activeVideos = array_filter($draft['services'], static fn (array $service): bool => $service['enabled'] && isset($videos[$service['key']]));
        $emitters = [
            'GoogleTagManager' => [Alert::DANGER, 'The GoogleTagManager module is active: it loads Google Tag Manager in the head with no consent. Disable it and enable the Tag Manager service of this module.'],
            'ReCaptcha' => [Alert::WARNING, 'The ReCaptcha module is active: it contacts Google on the pages with a form. Enable the reCAPTCHA service (placeholder on the form).'],
        ];
        foreach ($emitters as $code => [$severity, $message]) {
            if ($this->modules->isActive($code)) {
                $alerts[] = new Alert($severity, 'emitter_'.strtolower($code), $this->text->t($message), 'ccm_admin_services');
            }
        }
        if ([] === $activeVideos && $this->modules->isActive('TheliaBlocks')) {
            $alerts[] = new Alert(Alert::WARNING, 'emitter_theliablocks', $this->text->t('TheliaBlocks is active: its video blocks embed third-party players. Enable the video services you use (%services%): the iframe is then replaced by a placeholder until the visitor consents.', ['%services%' => implode(', ', $videos)]), 'ccm_admin_services');
        }
    }

    /**
     * @param array<string, mixed> $draft
     * @param list<Alert>          $alerts
     */
    private function services(array $draft, array &$alerts): void
    {
        $enabled = array_column(array_filter($draft['services'], static fn (array $service): bool => $service['enabled']), null, 'key');

        $google = array_filter(array_keys($enabled), fn (string $key): bool => 'googletagmanager' !== $key && ($this->catalogue->find($key)->googleTag ?? false));
        if (isset($enabled['googletagmanager']) && [] !== $google) {
            $alerts[] = new Alert(Alert::DANGER, 'gtag_twice', $this->text->t('gtag is loaded twice: Tag Manager and %services% are both active. Keep one.', ['%services%' => implode(', ', $google)]), 'ccm_admin_services');
        }

        $without = array_values(array_map(fn (array $service): string => $this->labels->label('services.'.$service['key'], $this->text->locale(), $draft), array_filter($enabled, fn (array $service): bool => '' === $this->controllers->effective($service))));
        if ([] !== $without && [] === $draft['presets']) {
            $alerts[] = new Alert(Alert::WARNING, 'controller_missing', $this->text->t('No responsible (legal entity) is set for: %services%. Visitors see the product name instead.', ['%services%' => implode(', ', $without)]), 'ccm_admin_services');
        }

        foreach ($enabled as $service) {
            if ('essential' === $service['purpose'] && isset($service['module'])) {
                $alerts[] = new Alert(Alert::WARNING, 'strictly_necessary_'.$service['key'], $this->text->t('The service "%name%" is declared strictly necessary and runs without consent. Justification: %justification%', ['%name%' => $service['name'], '%justification%' => $service['justification'] ?? '']), 'ccm_admin_services');
            }
        }
    }
}
