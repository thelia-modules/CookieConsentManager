<?php

declare(strict_types=1);

namespace CookieConsentManager\Validation;

use CookieConsentManager\Admin\ActiveLanguages;
use CookieConsentManager\Admin\CodeLabels;
use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Catalogue\CookieDurations;
use CookieConsentManager\CookieConsentManager;
use CookieConsentManager\Draft\DefaultSettings;
use CookieConsentManager\Draft\ServiceControllers;
use CookieConsentManager\Front\TemplateContext;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class DraftValidator
{
    public const MAX_COOKIE_DAYS = 7300;
    public const FRANCE_PRESET = 'fr-FR';

    public const KEY_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';
    public const MAX_RETENTION_MONTHS = 60;
    public const TEXT_LIMITS = ['title' => 120, 'body' => 1500, 'accept' => 60, 'reject' => 60, 'customize' => 60, 'close' => 80, 'allow' => 60, 'preferences' => 60, 'free_text' => 1500];
    public const REQUIRED_TEXTS = ['accept', 'reject', 'customize'];
    private const IMPLICIT_CONSENT = [
        '/\ben\s+(poursuivant|continuant|naviguant)\b/iu',
        '/\bpoursuite\s+de\s+(votre|la)\s+navigation\b/iu',
        '/\bby\s+(continuing|browsing|navigating|proceeding|using\s+this\s+(web)?site)\b/i',
        '/\bcontinuing\s+to\s+(browse|use|navigate)\b/i',
    ];
    private const EUROPEAN_REGIONS = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'IS', 'LI', 'NO', 'GB', 'CH', 'EU', 'EEA'];

    public const DISCONTINUED = 'universal-analytics';

    /** Issues that refuse an import outright: an address that is not a path of the shop or an https URL is never stored. */
    public const URL_ERROR_CODES = ['policy_url_invalid', 'script_url'];

    private ServiceControllers $controllers;
    private ActiveLanguages $languages;
    private CookieDurations $durations;

    public function __construct(
        private CatalogueReader $catalogue,
        private ConflictingModuleDetector $modules,
        private TranslatorInterface $translator,
        ?ServiceControllers $controllers = null,
        ?ActiveLanguages $languages = null,
        ?CookieDurations $durations = null,
    ) {
        $this->controllers = $controllers ?? new ServiceControllers(__DIR__.'/../Resources/catalogue/controllers.json');
        $this->languages = $languages ?? new ActiveLanguages();
        $this->durations = $durations ?? new CookieDurations(__DIR__.'/../Resources/catalogue/cookie-durations.json');
    }

    /**
     * @param array<string, mixed> $draft normalized draft
     *
     * @return list<ValidationIssue>
     */
    public function validate(array $draft): array
    {
        $issues = [];
        $purposes = array_column($draft['purposes'], 'code');

        $this->general($draft, $issues);

        $seen = [];
        foreach ($draft['services'] as $service) {
            $key = $service['key'];
            $field = 'services.'.$key;

            if (isset($seen[$key])) {
                $issues[] = $this->error('key_duplicate', $field, 'Service key "%key%" is declared twice.', ['%key%' => $key]);
            }
            $seen[$key] = true;

            if (1 !== preg_match(self::KEY_PATTERN, $key)) {
                $issues[] = $this->error('key_invalid', $field, 'Service key "%key%" is invalid (lowercase letters, digits, "-" and "_").', ['%key%' => $key]);
            }

            if (!\in_array($service['purpose'], [...$purposes, 'essential'], true)) {
                $issues[] = $this->error('unknown_purpose', $field.'.purpose', 'Unknown purpose "%purpose%".', ['%purpose%' => $service['purpose']]);
            }

            $this->essential($service, $field, $issues);

            if (isset($service['custom']) || isset($service['module'])) {
                $this->declared($service, $field, $issues);
            } else {
                $this->catalogueService($draft, $service, $field, $issues);
            }
        }

        $this->exempt($draft, $issues);
        $this->preset($draft, $issues);

        foreach ((array) ($draft['_meta']['environment_pending'] ?? []) as $path) {
            $issues[] = $this->error('environment_unconfirmed', (string) $path, 'The environment field "%path%" imported from another site is not confirmed.', ['%path%' => $path]);
        }

        return $issues;
    }

    /** @param list<ValidationIssue> $issues */
    public static function hasErrors(array $issues): bool
    {
        foreach ($issues as $issue) {
            if ($issue->isError()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed>  $draft
     * @param list<ValidationIssue> $issues
     */
    private function general(array $draft, array &$issues): void
    {
        $general = $draft['general'];
        if (!\in_array($general['layout'], ['bar', 'modal'], true)) {
            $issues[] = $this->error('layout', 'general.layout', 'The layout must be "bar" or "modal".');
        }
        if ($general['renewal_months'] < 1 || $general['renewal_months'] > 13) {
            $issues[] = $this->error('renewal_months', 'general.renewal_months', 'The renewal delay must be between 1 and 13 months.');
        }
        if (!\in_array($draft['consent_mode']['mode'], ['basic', 'advanced'], true)) {
            $issues[] = $this->error('consent_mode', 'consent_mode.mode', 'The Consent Mode must be "basic" or "advanced".');
        }
        if (!\in_array($draft['registry']['ip_mode'], ['none', 'truncated'], true)) {
            $issues[] = $this->error('ip_mode', 'registry.ip_mode', 'The IP option must be "none" or "truncated".');
        }
        if ($draft['registry']['retention_months'] < 1) {
            $issues[] = $this->error('retention', 'registry.retention_months', 'The retention must be at least one month.');
        } elseif ($draft['registry']['retention_months'] > self::MAX_RETENTION_MONTHS) {
            $issues[] = $this->error('retention_max', 'registry.retention_months', 'The retention cannot exceed %max% months.', ['%max%' => (string) self::MAX_RETENTION_MONTHS]);
        }
        $this->policyUrls($draft, $issues);
        $this->texts($draft, $issues);
        if (null === $draft['policy']['url'] || '' === $draft['policy']['url']) {
            $issues[] = new ValidationIssue(ValidationIssue::WARNING, 'policy_missing', 'policy.url', $this->trans('No privacy policy page is set.'));
        }
        foreach ($draft['consent_mode']['regions'] as $index => $region) {
            foreach ((array) ($region['default'] ?? []) as $signal => $state) {
                if (!\in_array($signal, DefaultSettings::SIGNALS, true)) {
                    $issues[] = $this->error('signal', 'consent_mode.regions.'.$index, 'Unknown Consent Mode signal "%signal%".', ['%signal%' => $signal]);
                } elseif ('granted' === $state && 'security_storage' !== $signal && self::coversEurope((array) $region['iso'])) {
                    $issues[] = $this->error('default_granted', 'consent_mode.regions.'.$index, 'The default "granted" for "%signal%" is refused for a region that includes the European Union or the United Kingdom: it equals a tracker set without consent.', ['%signal%' => $signal]);
                }
            }
        }
    }

    /**
     * @param array<string, mixed>  $service
     * @param list<ValidationIssue> $issues
     */
    private function essential(array $service, string $field, array &$issues): void
    {
        if ('essential' !== $service['purpose']) {
            return;
        }

        $necessary = (bool) ($service['strictly_necessary'] ?? false);
        if (!isset($service['module']) || !$necessary) {
            $issues[] = $this->error('essential_forbidden', $field.'.purpose', 'The purpose "essential" is reserved to a service declared "strictly necessary" by a module.');

            return;
        }

        if ('' === trim((string) ($service['justification'] ?? ''))) {
            $issues[] = $this->error('justification_missing', $field.'.justification', 'A strictly necessary service needs a written justification.');
        }
    }

    /**
     * @param array<string, mixed>  $service
     * @param list<ValidationIssue> $issues
     */
    private function declared(array $service, string $field, array &$issues): void
    {
        if (null !== $this->catalogue->find($service['key'])) {
            $issues[] = $this->error('key_collision', $field, 'The key "%key%" is already used by the catalogue.', ['%key%' => $service['key']]);
        }

        foreach (['name' => 'Name', 'controller' => 'Responsible', 'policy_url' => 'Privacy policy URL'] as $required => $label) {
            if ('' === $service[$required]) {
                $issues[] = $this->error('required', $field.'.'.$required, 'The field "%field%" is required.', ['%field%' => $this->trans($label)]);
            }
        }
        if ('' !== $service['policy_url'] && !self::isPolicyUrl($service['policy_url'])) {
            $issues[] = $this->error('policy_url_invalid', $field.'.policy_url', 'The address "%url%" is not valid: enter a path of the shop (/page) or an absolute https:// address.', ['%url%' => $service['policy_url']]);
        }

        if (!\in_array($service['kind'], ['script', 'custom', 'declarative'], true)) {
            $issues[] = $this->error('kind', $field.'.kind', 'Unknown service kind "%kind%".', ['%kind%' => $service['kind']]);

            return;
        }

        $scriptUrl = (string) ($service['script_url'] ?? '');
        if ('' !== $scriptUrl && 1 !== preg_match('~^https://[^\s"\'<>]+$~i', $scriptUrl)) {
            $issues[] = $this->error('script_url', $field.'.script_url', 'A script service needs an https URL.');

            return;
        }

        if (!$service['enabled']) {
            return;
        }

        if ('script' === $service['kind'] && 1 !== preg_match('~^https://[^\s"\'<>]+$~i', (string) ($service['script_url'] ?? ''))) {
            $issues[] = $this->error('script_url', $field.'.script_url', 'A script service needs an https URL.');
        }
        if ('custom' === $service['kind'] && '' === trim((string) ($service['code'] ?? ''))) {
            $issues[] = $this->error('code', $field.'.code', 'A custom service needs code.');
        }
    }

    /**
     * @param array<string, mixed>  $draft
     * @param array<string, mixed>  $service
     * @param list<ValidationIssue> $issues
     */
    private function catalogueService(array $draft, array $service, string $field, array &$issues): void
    {
        $entry = $this->catalogue->find($service['key']);
        if (self::DISCONTINUED === $entry?->excluded) {
            $issues[] = $this->error('service_discontinued', $field, 'The service "%key%" (Universal Analytics) was shut down by Google and is no longer offered: disable it and use Google Analytics 4 (service "gtag").', ['%key%' => $service['key']]);

            return;
        }

        if (null === $entry || null !== $entry->excluded) {
            $issues[] = $this->error('unknown_service', $field, 'The service "%key%" is not in the catalogue.', ['%key%' => $service['key']]);

            return;
        }

        foreach ((array) ($service['cookie_durations'] ?? []) as $cookie => $days) {
            if ($days > self::MAX_COOKIE_DAYS) {
                $issues[] = $this->error('cookie_duration', $field.'.cookie_durations.'.$cookie, 'The duration of the cookie "%cookie%" cannot exceed %max% days.', ['%cookie%' => (string) $cookie, '%max%' => (string) self::MAX_COOKIE_DAYS]);
            }
        }

        if (!$service['enabled']) {
            return;
        }

        if ('essential' !== $service['purpose'] && $service['purpose'] !== $entry->purpose) {
            $issues[] = $this->error('purpose_locked', $field.'.purpose', 'The service "%key%" belongs to the purpose "%purpose%" and cannot be moved to another one.', ['%key%' => $entry->name, '%purpose%' => $entry->purpose]);
        }

        if (!$entry->verified && !$draft['general']['experimental_services']) {
            $issues[] = $this->error('experimental', $field, 'The service "%key%" is experimental and experimental services are off.', ['%key%' => $service['key']]);
        }

        if ('essential' === $service['purpose'] || 'essential' === $entry->purpose) {
            $issues[] = $this->error('essential_forbidden', $field.'.purpose', 'The purpose "essential" is reserved to a service declared "strictly necessary" by a module.');
        }

        foreach ($entry->params as $param) {
            $value = $service['parameters'][$param['name']] ?? '';
            $parameterField = $field.'.parameters.'.$param['name'];
            $label = $param['label'][$this->locale()] ?? $param['label']['en'] ?? $param['name'];

            if ('' === $value) {
                if ($param['required']) {
                    $issues[] = $this->error('param_required', $parameterField, 'The field "%name%" is required.', ['%name%' => $label]);
                }
                continue;
            }

            if (null !== $param['pattern'] && 1 !== @preg_match('~'.str_replace('~', '\~', $param['pattern']).'~u', $value)) {
                $help = $param['help'][$this->locale()] ?? $param['help']['en'] ?? '';
                $issues[] = '' === $help
                    ? $this->error('param_pattern', $parameterField, 'The field "%name%" has an invalid format.', ['%name%' => $label])
                    : $this->error('param_pattern', $parameterField, 'The field "%name%" has an invalid format. Expected: %help%', ['%name%' => $label, '%help%' => $help]);
            }
        }

        if ('googletagmanager' === $service['key'] && $this->modules->isActive('GoogleTagManager')) {
            $issues[] = $this->error('gtm_module_active', $field, 'Google Tag Manager cannot be enabled while the GoogleTagManager module is active.');
        }
    }

    /**
     * @param array<string, mixed>  $draft
     * @param list<ValidationIssue> $issues
     */
    private function exempt(array $draft, array &$issues): void
    {
        $services = array_column($draft['services'], null, 'key');

        foreach ($draft['exempt_measurement'] as $index => $item) {
            $field = 'exempt_measurement.'.$index;
            $service = $services[$item['service']] ?? null;
            $entry = $this->catalogue->find($item['service']);

            if (null === $service || 'analytics' !== $service['purpose']) {
                $issues[] = $this->error('exempt_service', $field, 'Only a configured audience measurement service can be exempted.');
                continue;
            }
            if ((null !== $entry && $entry->googleTag) || 'gtag' === $item['service']) {
                $issues[] = $this->error('exempt_google', $field, 'A Google Analytics service is never eligible to the exemption.');
            }
            if (!(null !== $entry && $entry->googleTag) && 'gtag' !== $item['service'] && !ExemptionWhitelist::allows($item['service'])) {
                $issues[] = $this->error('exempt_not_allowed', $field, 'This service is not in the list of tools that can be exempted from consent.');
            }
            $missing = array_keys(array_filter($item['conditions'], static fn (bool $confirmed): bool => !$confirmed));
            if ([] !== $missing) {
                $labels = array_map(fn (string $condition): string => $this->trans(CodeLabels::english('condition', $condition)), $missing);
                $issues[] = $this->error('exempt_conditions', $field, 'Condition(s) not confirmed: %conditions%.', ['%conditions%' => implode(', ', $labels)]);
            }
        }
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
                $issues[] = $this->error('controller_missing_preset', 'services.'.$service['key'].'.controller', 'The service "%name%" has no responsible (legal entity): visitors would read the name of the product. Enter the legal entity of its publisher.', ['%name%' => $name]);
            }
        }

        $main = $this->languages->mainCode();
        $this->cookieDurations($draft, $main, $issues);

        foreach ($this->languagesUnderFrenchPreset($draft) as $language) {
            $url = trim(TemplateContext::policyUrl($draft['policy'], $language));
            if ('' === $url) {
                $issues[] = $this->error('policy_url_preset_missing', 'policy.urls.'.$language, 'The French preset needs the address of the cookie policy page of the language "%language%".', ['%language%' => $language]);
            } elseif (TermsPageUrl::matches($url)) {
                $issues[] = $this->error('policy_url_terms', 'policy.urls.'.$language, 'The address "%url%" of the language "%language%" is a page of terms and conditions, not a cookie policy: publish a page dedicated to cookies.', ['%url%' => $url, '%language%' => $language]);
            }
        }
    }

    /**
     * The languages whose cookie policy address the French preset checks: those it is attached to, and every active language when it is attached to the main one.
     *
     * @param array<string, mixed> $draft normalized
     *
     * @return list<string>
     */
    public function languagesUnderFrenchPreset(array $draft): array
    {
        $languages = array_keys(array_filter($draft['presets'], static fn (string $code): bool => self::FRANCE_PRESET === $code));
        if (self::FRANCE_PRESET === ($draft['presets'][$this->languages->mainCode()] ?? null)) {
            $languages = [...$languages, ...$this->languages->codes()];
        }

        return array_values(array_unique(array_map('strval', $languages)));
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
            $issues[] = $this->error('cookie_duration_missing', 'services.'.$key.'.cookie_durations', 'The service "%name%" declares cookies with no known lifetime (%cookies%): visitors would not be told how long they last. Enter the lifetime of each cookie on the service, or disable it.', ['%name%' => (string) $name, '%cookies%' => implode(', ', $cookies)]);
        }
    }

    /** @param list<string> $iso an empty list is the global default */
    private static function coversEurope(array $iso): bool
    {
        if ([] === $iso) {
            return true;
        }
        foreach ($iso as $code) {
            if (\in_array(strtoupper(explode('-', $code)[0]), self::EUROPEAN_REGIONS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed>  $draft
     * @param list<ValidationIssue> $issues
     */
    private function policyUrls(array $draft, array &$issues): void
    {
        $urls = ['policy.url' => (string) ($draft['policy']['url'] ?? '')];
        foreach ((array) ($draft['policy']['urls'] ?? []) as $language => $url) {
            $urls['policy.urls.'.$language] = (string) $url;
        }
        foreach ($urls as $field => $url) {
            if ('' !== $url && !self::isPolicyUrl($url)) {
                $issues[] = $this->error('policy_url_invalid', $field, 'The address "%url%" is not valid: enter a path of the shop (/page) or an absolute https:// address.', ['%url%' => $url]);
            }
        }
        if (mb_strlen((string) ($draft['policy']['page_title'] ?? '')) > 200) {
            $issues[] = $this->error('too_long', 'policy.page_title', 'The text is too long (%max% characters at most).', ['%max%' => '200']);
        }
    }

    public static function isPolicyUrl(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return !str_starts_with($url, '//') && 1 !== preg_match('/\s/', $url);
        }

        return 1 === preg_match('~^https://[^\s"\'<>/]+[^\s"\'<>]*$~i', $url);
    }

    /**
     * @param array<string, mixed>  $draft
     * @param list<ValidationIssue> $issues
     */
    private function texts(array $draft, array &$issues): void
    {
        foreach ($draft['texts'] as $language => $row) {
            foreach (self::TEXT_LIMITS as $key => $limit) {
                $field = 'texts.'.$language.'.'.$key;
                $value = trim((string) ($row[$key] ?? ''));
                if (\in_array($key, self::REQUIRED_TEXTS, true) && '' === $value) {
                    $issues[] = $this->error('text_empty', $field, 'This label cannot be empty.');
                } elseif (mb_strlen($value) > $limit) {
                    $issues[] = $this->error('too_long', $field, 'The text is too long (%max% characters at most).', ['%max%' => (string) $limit]);
                }
            }

            foreach (['title', 'body', 'free_text'] as $key) {
                foreach (self::IMPLICIT_CONSENT as $pattern) {
                    if (1 === preg_match($pattern, (string) ($row[$key] ?? ''))) {
                        $issues[] = new ValidationIssue(ValidationIssue::WARNING, 'implicit_consent', 'texts.'.$language.'.'.$key, $this->trans('This text suggests that continuing to browse is a consent: it is not one. Remove it.'));
                        break;
                    }
                }
            }

            $accept = mb_strlen(trim((string) ($row['accept'] ?? '')));
            $reject = mb_strlen(trim((string) ($row['reject'] ?? '')));
            if ($accept > 0 && $reject > 0 && abs($accept - $reject) / max($accept, $reject) > 0.5) {
                $issues[] = new ValidationIssue(ValidationIssue::WARNING, 'label_gap', 'texts.'.$language.'.reject', $this->trans('The Accept and Reject labels differ by more than 50 % in length: the two choices must look equal.'));
            }
        }
    }

    private function locale(): string
    {
        return $this->translator instanceof LocaleAwareInterface ? substr($this->translator->getLocale(), 0, 2) : 'en';
    }

    /** @param array<string, string> $parameters */
    private function error(string $code, string $field, string $message, array $parameters = []): ValidationIssue
    {
        return new ValidationIssue(ValidationIssue::ERROR, $code, $field, $this->trans($message, $parameters));
    }

    /** @param array<string, string> $parameters */
    private function trans(string $message, array $parameters = []): string
    {
        return $this->translator->trans($message, $parameters, CookieConsentManager::DOMAIN_NAME);
    }
}
