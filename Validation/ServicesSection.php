<?php

declare(strict_types=1);

namespace CookieConsentManager\Validation;

use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\CookieConsentManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Every service of the draft: key, purpose, declared services, catalogue rules and parameters. */
final readonly class ServicesSection implements SectionValidator
{
    /** @param list<array{service_id: string, attributes: array<string, mixed>}> $declared */
    public function __construct(
        private CatalogueReader $catalogue,
        private ConflictingModuleDetector $modules,
        private IssueFactory $issues,
        #[Autowire(param: CookieConsentManager::PARAMETER_DECLARED_SERVICES)]
        private array $declared = [],
    ) {
    }

    public static function priority(): int
    {
        return 900;
    }

    public function validate(array $draft): array
    {
        $issues = [];
        $purposes = array_column($draft['purposes'], 'code');

        $seen = [];
        foreach ($draft['services'] as $service) {
            $key = $service['key'];
            $field = 'services.'.$key;

            if (isset($seen[$key])) {
                $issues[] = $this->issues->error('key_duplicate', $field, 'Service key "%key%" is declared twice.', ['%key%' => $key]);
            }
            $seen[$key] = true;

            if (1 !== preg_match(DraftValidator::KEY_PATTERN, $key)) {
                $issues[] = $this->issues->error('key_invalid', $field, 'Service key "%key%" is invalid (lowercase letters, digits, "-" and "_").', ['%key%' => $key]);
            }

            if (!\in_array($service['purpose'], [...$purposes, 'essential'], true)) {
                $issues[] = $this->issues->error('unknown_purpose', $field.'.purpose', 'Unknown purpose "%purpose%".', ['%purpose%' => $service['purpose']]);
            }

            $this->essential($service, $field, $issues);

            if (isset($service['module'])) {
                $this->moduleService($service, $field, $issues);
            }

            if (isset($service['custom']) || isset($service['module'])) {
                $this->declared($service, $field, $issues);
            } else {
                $this->catalogueService($draft, $service, $field, $issues);
            }
        }

        return $issues;
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
            $issues[] = $this->issues->error('essential_forbidden', $field.'.purpose', 'The purpose "essential" is reserved to a service declared "strictly necessary" by a module.');

            return;
        }

        if ('' === trim((string) ($service['justification'] ?? ''))) {
            $issues[] = $this->issues->error('justification_missing', $field.'.justification', 'A strictly necessary service needs a written justification.');
        }
    }

    /**
     * A service flagged "module" must be one a module really declares with the `ccm.service` tag, and may not go beyond the declaration.
     *
     * @param array<string, mixed>  $service
     * @param list<ValidationIssue> $issues
     */
    private function moduleService(array $service, string $field, array &$issues): void
    {
        $attributes = null;
        foreach ($this->declared as $declaration) {
            if (($declaration['attributes']['key'] ?? null) === $service['key']) {
                $attributes = $declaration['attributes'];
                break;
            }
        }

        if (null === $attributes) {
            $issues[] = $this->issues->error('module_undeclared', $field, 'The service "%key%" claims to come from a module, but no active module declares it.', ['%key%' => $service['key']]);

            return;
        }

        if ('' !== trim((string) ($service['code'] ?? ''))) {
            $issues[] = $this->issues->error('module_code', $field.'.code', 'A service declared by a module cannot carry code.');
        }

        $declaredUrl = (string) ($attributes['script'] ?? '');
        if ((string) ($service['script_url'] ?? '') !== $declaredUrl) {
            $issues[] = $this->issues->error('module_script_url', $field.'.script_url', 'The script address of a service declared by a module must be the declared one.');
        }

        $declaredNecessary = (bool) ($attributes['strictly_necessary'] ?? false);
        if (!$declaredNecessary && (true === ($service['strictly_necessary'] ?? false) || 'essential' === $service['purpose'])) {
            $issues[] = $this->issues->error('module_necessary', $field.'.purpose', 'The module that declares the service "%key%" does not declare it strictly necessary.', ['%key%' => $service['key']]);
        }
    }

    /**
     * @param array<string, mixed>  $service
     * @param list<ValidationIssue> $issues
     */
    private function declared(array $service, string $field, array &$issues): void
    {
        if (null !== $this->catalogue->find($service['key'])) {
            $issues[] = $this->issues->error('key_collision', $field, 'The key "%key%" is already used by the catalogue.', ['%key%' => $service['key']]);
        }

        foreach (['name' => 'Name', 'controller' => 'Responsible', 'policy_url' => 'Privacy policy URL'] as $required => $label) {
            if ('' === $service[$required]) {
                $issues[] = $this->issues->error('required', $field.'.'.$required, 'The field "%field%" is required.', ['%field%' => $this->issues->trans($label)]);
            }
        }
        if ('' !== $service['policy_url'] && !DraftValidator::isPolicyUrl($service['policy_url'])) {
            $issues[] = $this->issues->error('policy_url_invalid', $field.'.policy_url', 'The address "%url%" is not valid: enter a path of the shop (/page) or an absolute https:// address.', ['%url%' => $service['policy_url']]);
        }

        if (!\in_array($service['kind'], ['script', 'custom', 'declarative'], true)) {
            $issues[] = $this->issues->error('kind', $field.'.kind', 'Unknown service kind "%kind%".', ['%kind%' => $service['kind']]);

            return;
        }

        $scriptUrl = (string) ($service['script_url'] ?? '');
        if ('' !== $scriptUrl && 1 !== preg_match('~^https://[^\s"\'<>]+$~i', $scriptUrl)) {
            $issues[] = $this->issues->error('script_url', $field.'.script_url', 'A script service needs an https URL.');

            return;
        }

        if (!$service['enabled']) {
            return;
        }

        if ('script' === $service['kind'] && 1 !== preg_match('~^https://[^\s"\'<>]+$~i', (string) ($service['script_url'] ?? ''))) {
            $issues[] = $this->issues->error('script_url', $field.'.script_url', 'A script service needs an https URL.');
        }
        if ('custom' === $service['kind'] && '' === trim((string) ($service['code'] ?? ''))) {
            $issues[] = $this->issues->error('code', $field.'.code', 'A custom service needs code.');
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
        if (DraftValidator::DISCONTINUED === $entry?->excluded) {
            $issues[] = $this->issues->error('service_discontinued', $field, 'The service "%key%" (Universal Analytics) was shut down by Google and is no longer offered: disable it and use Google Analytics 4 (service "gtag").', ['%key%' => $service['key']]);

            return;
        }

        if (null === $entry || null !== $entry->excluded) {
            $issues[] = $this->issues->error('unknown_service', $field, 'The service "%key%" is not in the catalogue.', ['%key%' => $service['key']]);

            return;
        }

        foreach ((array) ($service['cookie_durations'] ?? []) as $cookie => $days) {
            if ($days > DraftValidator::MAX_COOKIE_DAYS) {
                $issues[] = $this->issues->error('cookie_duration', $field.'.cookie_durations.'.$cookie, 'The duration of the cookie "%cookie%" cannot exceed %max% days.', ['%cookie%' => (string) $cookie, '%max%' => (string) DraftValidator::MAX_COOKIE_DAYS]);
            }
        }

        if (!$service['enabled']) {
            return;
        }

        if ('essential' !== $service['purpose'] && $service['purpose'] !== $entry->purpose) {
            $issues[] = $this->issues->error('purpose_locked', $field.'.purpose', 'The service "%key%" belongs to the purpose "%purpose%" and cannot be moved to another one.', ['%key%' => $entry->name, '%purpose%' => $entry->purpose]);
        }

        if (!$entry->verified && !$draft['general']['experimental_services']) {
            $issues[] = $this->issues->error('experimental', $field, 'The service "%key%" is experimental and experimental services are off.', ['%key%' => $service['key']]);
        }

        if ('essential' === $service['purpose'] || 'essential' === $entry->purpose) {
            $issues[] = $this->issues->error('essential_forbidden', $field.'.purpose', 'The purpose "essential" is reserved to a service declared "strictly necessary" by a module.');
        }

        foreach ($entry->params as $param) {
            $value = $service['parameters'][$param['name']] ?? '';
            $parameterField = $field.'.parameters.'.$param['name'];
            $label = $param['label'][$this->issues->locale()] ?? $param['label']['en'] ?? $param['name'];

            if ('' === $value) {
                if ($param['required']) {
                    $issues[] = $this->issues->error('param_required', $parameterField, 'The field "%name%" is required.', ['%name%' => $label]);
                }
                continue;
            }

            if (null !== $param['pattern'] && 1 !== @preg_match('~'.str_replace('~', '\~', $param['pattern']).'~u', $value)) {
                $help = $param['help'][$this->issues->locale()] ?? $param['help']['en'] ?? '';
                $issues[] = '' === $help
                    ? $this->issues->error('param_pattern', $parameterField, 'The field "%name%" has an invalid format.', ['%name%' => $label])
                    : $this->issues->error('param_pattern', $parameterField, 'The field "%name%" has an invalid format. Expected: %help%', ['%name%' => $label, '%help%' => $help]);
            }
        }

        if ('googletagmanager' === $service['key'] && $this->modules->isActive('GoogleTagManager')) {
            $issues[] = $this->issues->error('gtm_module_active', $field, 'Google Tag Manager cannot be enabled while the GoogleTagManager module is active.');
        }
    }
}
