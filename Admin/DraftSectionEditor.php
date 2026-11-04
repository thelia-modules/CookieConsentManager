<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Draft\DefaultSettings;

/** Turns the posted form of one screen into a candidate draft; nothing is stored here. */
final readonly class DraftSectionEditor
{
    private const GENERAL_FLAGS = ['close_button', 'floating_button', 'reload_on_script_withdraw', 'block_embedded_iframes', 'experimental_services'];

    public function __construct(private CatalogueReader $catalogue)
    {
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function general(array $draft, array $input): array
    {
        $general = (array) ($input['general'] ?? []);
        foreach (self::GENERAL_FLAGS as $flag) {
            $draft['general'][$flag] = isset($general[$flag]);
        }
        if (isset($general['renewal_months'])) {
            $draft['general']['renewal_months'] = (int) $general['renewal_months'];
        }

        $policy = (array) ($input['policy'] ?? []);
        $draft['policy']['url'] = $this->nullable($policy['url'] ?? null);
        $draft['policy']['page_title'] = $this->nullable($policy['page_title'] ?? null);
        if (isset($policy['urls'])) {
            $draft['policy']['urls'] = array_filter(array_map(static fn (mixed $url): string => trim((string) $url), (array) $policy['urls']), static fn (string $url): bool => '' !== $url);
        }

        return $draft;
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function appearance(array $draft, array $input): array
    {
        $draft['general']['layout'] = (string) ($input['layout'] ?? $draft['general']['layout']);
        foreach ((array) ($input['colors'] ?? []) as $name => $value) {
            if (isset($draft['appearance']['colors'][$name])) {
                $draft['appearance']['colors'][$name] = strtolower(trim((string) $value));
            }
        }

        return $draft;
    }

    /**
     * A language left entirely empty is not stored: it keeps falling back to English.
     *
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function texts(array $draft, array $input): array
    {
        foreach ((array) ($input['texts'] ?? []) as $locale => $values) {
            if (1 !== preg_match('/^[a-z]{2}$/', (string) $locale)) {
                continue;
            }
            $values = array_map(static fn (mixed $value): string => trim((string) $value), (array) $values);
            if ('' === implode('', $values) && !isset($draft['texts'][$locale])) {
                continue;
            }
            $draft['texts'][$locale] = array_intersect_key($values, DefaultSettings::all()['texts']['en']);
        }

        return $draft;
    }

    /**
     * @param array<string, mixed> $draft
     *
     * @return array<string, mixed>
     */
    public function resetTexts(array $draft, string $locale): array
    {
        unset($draft['texts'][$locale]);

        return $draft;
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function purposes(array $draft, array $input): array
    {
        $purposes = [];
        foreach ((array) ($input['purposes'] ?? []) as $code => $row) {
            $row = (array) $row;
            $purposes[] = [
                'code' => (string) $code,
                'order' => (int) ($row['order'] ?? 0),
                'consent_mode' => array_values(array_intersect(array_keys((array) ($row['consent_mode'] ?? [])), DefaultSettings::SIGNALS)),
                'label' => $this->localized($row['label'] ?? []),
                'description' => $this->localized($row['description'] ?? []),
            ];
        }

        $new = (array) ($input['new_purpose'] ?? []);
        $code = strtolower(trim((string) ($new['code'] ?? '')));
        if (1 === preg_match('/^[a-z][a-z0-9_]{0,63}$/', $code) && 'essential' !== $code && !\in_array($code, array_column($purposes, 'code'), true)) {
            $purposes[] = [
                'code' => $code,
                'order' => \count($purposes) + 1,
                'consent_mode' => [],
                'label' => $this->localized($new['label'] ?? []),
                'description' => $this->localized($new['description'] ?? []),
            ];
        }

        $draft['purposes'] = $purposes;

        return $draft;
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function catalogueService(array $draft, string $key, array $input): array
    {
        $entry = $this->catalogue->find($key);
        if (null === $entry) {
            return $draft;
        }

        $parameters = [];
        foreach ($entry->params as $param) {
            $value = trim((string) (((array) ($input['parameters'] ?? []))[$param['name']] ?? ''));
            if ('' !== $value) {
                $parameters[$param['name']] = $value;
            }
        }

        $service = [
            'key' => $key,
            'purpose' => $entry->purpose,
            'enabled' => isset($input['enabled']),
            'parameters' => $parameters,
        ];
        $durations = [];
        foreach ((array) ($input['cookie_durations'] ?? []) as $name => $days) {
            if (\in_array((string) $name, $entry->cookies, true) && is_numeric(trim((string) $days))) {
                $durations[(string) $name] = max(0, (int) trim((string) $days));
            }
        }
        if ([] !== $durations) {
            $service['cookie_durations'] = $durations;
        }
        $existing = array_column($draft['services'], null, 'key')[$key]['controller'] ?? '';
        if ('' !== $controller = trim((string) ($input['controller'] ?? $existing))) {
            $service['controller'] = $controller;
        }

        return $this->putService($draft, $service);
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function customService(array $draft, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $key = strtolower(trim((string) ($input['key'] ?? '')));
        if ('' === $key) {
            $key = 'custom_'.trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_');
        }

        $cookies = [];
        foreach (preg_split('/\R/', (string) ($input['cookies'] ?? '')) ?: [] as $line) {
            $parts = array_map('trim', explode(';', $line));
            if ('' === $parts[0]) {
                continue;
            }
            $cookies[] = array_filter([
                'name' => $parts[0],
                'domain' => '' !== ($parts[1] ?? '') ? $parts[1] : null,
                'duration_days' => '' !== ($parts[2] ?? '') ? (int) $parts[2] : null,
                'description' => '' !== ($parts[3] ?? '') ? $parts[3] : null,
            ], static fn (mixed $value): bool => null !== $value);
        }

        $service = [
            'key' => $key,
            'custom' => true,
            'kind' => (string) ($input['kind'] ?? 'declarative'),
            'purpose' => (string) ($input['purpose'] ?? ''),
            'enabled' => isset($input['enabled']),
            'name' => $name,
            'controller' => trim((string) ($input['controller'] ?? '')),
            'policy_url' => trim((string) ($input['policy_url'] ?? '')),
            'cookies' => $cookies,
            'script_url' => trim((string) ($input['script_url'] ?? '')),
            'code' => trim((string) ($input['code'] ?? '')),
        ];

        return $this->putService($draft, $service);
    }

    /**
     * @param array<string, mixed> $draft
     *
     * @return array<string, mixed>
     */
    public function removeCustomService(array $draft, string $key): array
    {
        $draft['services'] = array_values(array_filter(
            $draft['services'],
            static fn (array $service): bool => $service['key'] !== $key || !isset($service['custom']),
        ));

        return $draft;
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function proposedService(array $draft, string $key, array $input): array
    {
        foreach ($draft['services'] as $index => $service) {
            if ($service['key'] !== $key || !isset($service['module'])) {
                continue;
            }
            $service['enabled'] = isset($input['enabled']);
            $service['proposed'] = false;
            $service['purpose'] = (string) ($input['purpose'] ?? $service['purpose']);
            $justification = trim((string) ($input['justification'] ?? ''));
            if ('' !== $justification) {
                $service['justification'] = $justification;
            } else {
                unset($service['justification']);
            }
            $draft['services'][$index] = $service;
        }

        return $draft;
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function consentMode(array $draft, array $input): array
    {
        $mode = (array) ($input['consent_mode'] ?? []);
        $draft['consent_mode']['enabled'] = isset($mode['enabled']);
        $draft['consent_mode']['mode'] = (string) ($mode['mode'] ?? 'basic');
        $draft['consent_mode']['ads_data_redaction'] = isset($mode['ads_data_redaction']);
        $draft['consent_mode']['url_passthrough'] = isset($mode['url_passthrough']);
        $draft['consent_mode']['uet']['enabled'] = isset($mode['uet']);
        if (isset($mode['wait_for_update_ms'])) {
            $draft['consent_mode']['wait_for_update_ms'] = max(0, (int) $mode['wait_for_update_ms']);
        }

        // The regions only exist in the form in advanced mode: a basic-mode save keeps what is stored.
        if (isset($input['regions'])) {
            $regions = [];
            foreach ((array) $input['regions'] as $row) {
                $row = (array) $row;
                $iso = array_values(array_filter(array_map(static fn (string $code): string => strtoupper(trim($code)), explode(',', (string) ($row['iso'] ?? '')))));
                if ([] === $iso) {
                    continue;
                }
                $signals = [];
                foreach ((array) ($row['signals'] ?? []) as $signal => $state) {
                    if ('' !== $state) {
                        $signals[(string) $signal] = (string) $state;
                    }
                }
                $regions[] = ['iso' => $iso, 'default' => $signals];
            }
            $draft['consent_mode']['regions'] = $regions;
        }

        return $draft;
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function exemptMeasurement(array $draft, array $input): array
    {
        $items = [];
        foreach ((array) ($input['exempt'] ?? []) as $service => $row) {
            $row = (array) $row;
            if (!isset($row['enabled'])) {
                continue;
            }
            $conditions = [];
            foreach (DefaultSettings::EXEMPT_CONDITIONS as $condition) {
                $conditions[$condition] = isset(((array) ($row['conditions'] ?? []))[$condition]);
            }
            $items[] = ['service' => (string) $service, 'conditions' => $conditions];
        }
        $draft['exempt_measurement'] = $items;

        return $draft;
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function registryOptions(array $draft, array $input): array
    {
        $registry = (array) ($input['registry'] ?? []);
        $draft['registry']['retention_months'] = (int) ($registry['retention_months'] ?? $draft['registry']['retention_months']);
        $draft['registry']['ip_mode'] = isset($registry['ip_truncated']) ? 'truncated' : 'none';
        $draft['registry']['link_customer'] = isset($registry['link_customer']);

        return $draft;
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $service
     *
     * @return array<string, mixed>
     */
    private function putService(array $draft, array $service): array
    {
        $replaced = false;
        foreach ($draft['services'] as $index => $existing) {
            if ($existing['key'] === $service['key']) {
                $draft['services'][$index] = $service;
                $replaced = true;
            }
        }
        if (!$replaced) {
            $draft['services'][] = $service;
        }

        return $draft;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    /** @return array<string, string> */
    private function localized(mixed $values): array
    {
        return array_map(static fn (mixed $value): string => trim((string) $value), (array) $values);
    }
}
