<?php

declare(strict_types=1);

namespace CookieConsentManager\Validation;

use CookieConsentManager\Admin\ActiveLanguages;
use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Catalogue\CookieDurations;
use CookieConsentManager\Draft\ServiceControllers;
use CookieConsentManager\Front\TemplateContext;

/** What a country preset makes mandatory. */
final readonly class PresetSection implements SectionValidator
{
    public function __construct(
        private CatalogueReader $catalogue,
        private ServiceControllers $controllers,
        private ActiveLanguages $languages,
        private CookieDurations $durations,
        private FrenchPresetLanguages $frenchLanguages,
        private IssueFactory $issues,
    ) {
    }

    public static function priority(): int
    {
        return 700;
    }

    public function validate(array $draft): array
    {
        $issues = [];
        $this->preset($draft, $issues);

        return $issues;
    }

    /**
     * What a country preset makes mandatory: every active service names its responsible, and the French
     * preset needs a page of its own for the cookie policy (not the terms of sale).
     *
     * @param array<string, mixed>  $draft
     * @param list<ValidationIssue> $issues
     */
    private function preset(array $draft, array &$issues): void
    {
        if ([] === $draft['presets']) {
            return;
        }

        foreach ($draft['services'] as $service) {
            if ($service['enabled'] && !isset($service['custom']) && !isset($service['module']) && '' === $this->controllers->effective($service)) {
                $name = $this->catalogue->find($service['key'])->name ?? $service['key'];
                $issues[] = $this->issues->error('controller_missing_preset', 'services.'.$service['key'].'.controller', 'The service "%name%" has no responsible (legal entity): visitors would read the name of the product. Enter the legal entity of its publisher.', ['%name%' => $name]);
            }
        }

        $main = $this->languages->mainCode();
        $this->cookieDurations($draft, $main, $issues);

        foreach ($this->frenchLanguages->of($draft) as $language) {
            $url = trim(TemplateContext::policyUrl($draft['policy'], $language));
            if ('' === $url) {
                $issues[] = $this->issues->error('policy_url_preset_missing', 'policy.urls.'.$language, 'The French preset needs the address of the cookie policy page of the language "%language%".', ['%language%' => $language]);
            } elseif (TermsPageUrl::matches($url)) {
                $issues[] = $this->issues->error('policy_url_terms', 'policy.urls.'.$language, 'The address "%url%" of the language "%language%" is a page of terms and conditions, not a cookie policy: publish a page dedicated to cookies.', ['%url%' => $url, '%language%' => $language]);
            }
        }
    }

    /**
     * Under a preset attached to the main language, an active service with a cookie of unknown lifetime blocks the publication.
     *
     * @param array<string, mixed>  $draft
     * @param list<ValidationIssue> $issues
     */
    private function cookieDurations(array $draft, string $main, array &$issues): void
    {
        if (!isset($draft['presets'][$main])) {
            return;
        }

        $services = array_column($draft['services'], null, 'key');
        foreach ($this->durations->withoutLifetime($draft['services'], $this->catalogue) as $key => $cookies) {
            $name = $this->catalogue->find($key)->name ?? ($services[$key]['name'] ?? $key);
            $issues[] = $this->issues->error('cookie_duration_missing', 'services.'.$key.'.cookie_durations', 'The service "%name%" declares cookies with no known lifetime (%cookies%): visitors would not be told how long they last. Enter the lifetime of each cookie on the service, or disable it.', ['%name%' => (string) $name, '%cookies%' => implode(', ', $cookies)]);
        }
    }
}
