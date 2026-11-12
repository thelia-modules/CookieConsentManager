<?php

declare(strict_types=1);

namespace CookieConsentManager\Draft;

/**
 * One canonical form for a draft, so that two drafts holding the same configuration are
 * equal whatever way they were produced (form, import, restoration of a version).
 */
final class DraftNormalizer
{
    /**
     * @param array<string, mixed> $draft
     *
     * @return array<string, mixed>
     */
    public function normalize(array $draft): array
    {
        $defaults = DefaultSettings::all();
        $normalized = [];

        $normalized['general'] = $this->mergeMap($defaults['general'], $draft['general'] ?? []);
        $normalized['policy'] = $this->mergeMap($defaults['policy'], $draft['policy'] ?? []);
        $normalized['policy']['urls'] = $this->policyUrls((array) ($draft['policy']['urls'] ?? []));
        $normalized['texts'] = $this->texts($defaults['texts'], (array) ($draft['texts'] ?? []));
        $normalized['appearance'] = ['colors' => $this->mergeMap($defaults['appearance']['colors'], $draft['appearance']['colors'] ?? [])];
        $normalized['purposes'] = $this->purposes($draft['purposes'] ?? $defaults['purposes']);

        $consentMode = $this->mergeMap($defaults['consent_mode'], $draft['consent_mode'] ?? []);
        $consentMode['uet'] = $this->mergeMap($defaults['consent_mode']['uet'], $draft['consent_mode']['uet'] ?? []);
        $consentMode['regions'] = array_values(array_map(static fn (array $region): array => [
            'iso' => array_values(array_map('strval', (array) $region['iso'])),
            'default' => array_map('strval', (array) ($region['default'] ?? [])),
        ], (array) $consentMode['regions']));
        $normalized['consent_mode'] = $consentMode;

        $normalized['exempt_measurement'] = array_values(array_map(
            fn (array $item): array => [
                'service' => (string) $item['service'],
                'conditions' => $this->conditions((array) ($item['conditions'] ?? [])),
            ],
            (array) ($draft['exempt_measurement'] ?? []),
        ));
        $normalized['registry'] = $this->mergeMap($defaults['registry'], $draft['registry'] ?? []);
        $normalized['presets'] = $this->presets((array) ($draft['presets'] ?? []));

        $services = array_map($this->service(...), array_values((array) ($draft['services'] ?? [])));
        usort($services, static fn (array $a, array $b): int => strcmp($a['key'], $b['key']));
        $normalized['services'] = $services;

        if (isset($draft['_meta']) && [] !== $draft['_meta']) {
            $normalized['_meta'] = $draft['_meta'];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function mergeMap(array $defaults, array $values): array
    {
        $merged = $defaults;
        foreach ($defaults as $key => $default) {
            if (\array_key_exists($key, $values) && !\is_array($default)) {
                $merged[$key] = $this->cast($default, $values[$key]);
            } elseif (\array_key_exists($key, $values) && [] === $default && \is_array($values[$key])) {
                $merged[$key] = $values[$key];
            }
        }

        return $merged;
    }

    private function cast(mixed $default, mixed $value): mixed
    {
        return match (true) {
            \is_bool($default) => (bool) $value,
            \is_int($default) => (int) $value,
            null === $default => null === $value || '' === $value ? null : (string) $value,
            default => \is_string($value) ? $value : (string) $value,
        };
    }

    /**
     * @param array<string, mixed> $urls
     *
     * @return array<string, string>
     */
    private function policyUrls(array $urls): array
    {
        $result = [];
        foreach ($urls as $language => $url) {
            if (1 === preg_match('/^[a-z]{2}$/D', (string) $language) && '' !== trim((string) $url)) {
                $result[(string) $language] = trim((string) $url);
            }
        }
        ksort($result);

        return $result;
    }

    /**
     * Preset attached to each language: `fr` => `fr-FR`.
     *
     * @param array<string, mixed> $presets
     *
     * @return array<string, string>
     */
    private function presets(array $presets): array
    {
        $result = [];
        foreach ($presets as $language => $code) {
            if (1 === preg_match('/^[a-z]{2}$/D', (string) $language) && 1 === preg_match('/^[a-z]{2}-[A-Z]{2}$/D', (string) $code)) {
                $result[(string) $language] = (string) $code;
            }
        }
        ksort($result);

        return $result;
    }

    /**
     * @param array<string, array<string, string>> $defaults
     * @param array<string, mixed>                 $texts
     *
     * @return array<string, array<string, string>>
     */
    private function texts(array $defaults, array $texts): array
    {
        $result = [];
        $locales = array_unique([...array_keys($defaults), ...array_keys($texts)]);
        sort($locales);

        foreach ($locales as $locale) {
            $given = (array) ($texts[$locale] ?? []);
            $row = [];
            foreach ($defaults['en'] as $key => $english) {
                $row[$key] = \array_key_exists($key, $given) ? (string) $given[$key] : ('en' === $locale ? $english : '');
            }
            $result[$locale] = $row;
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $purposes
     *
     * @return list<array<string, mixed>>
     */
    private function purposes(array $purposes): array
    {
        $result = array_map(static fn (array $purpose): array => [
            'code' => (string) $purpose['code'],
            'order' => (int) ($purpose['order'] ?? 0),
            'consent_mode' => array_values(array_map('strval', (array) ($purpose['consent_mode'] ?? []))),
            'label' => array_map('strval', (array) ($purpose['label'] ?? [])),
            'description' => array_map('strval', (array) ($purpose['description'] ?? [])),
        ], array_values($purposes));
        usort($result, static fn (array $a, array $b): int => [$a['order'], $a['code']] <=> [$b['order'], $b['code']]);

        return $result;
    }

    /**
     * @param array<string, mixed> $conditions
     *
     * @return array<string, bool>
     */
    private function conditions(array $conditions): array
    {
        $result = [];
        foreach (DefaultSettings::EXEMPT_CONDITIONS as $condition) {
            $result[$condition] = (bool) ($conditions[$condition] ?? false);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $service
     *
     * @return array<string, mixed>
     */
    private function service(array $service): array
    {
        $base = [
            'key' => (string) $service['key'],
            'purpose' => (string) $service['purpose'],
            'enabled' => (bool) ($service['enabled'] ?? false),
        ];

        $isCustom = (bool) ($service['custom'] ?? false);
        $isModule = (bool) ($service['module'] ?? false);

        if (!$isCustom && !$isModule) {
            $parameters = array_map('strval', (array) ($service['parameters'] ?? []));
            ksort($parameters);

            $catalogue = $base + ['parameters' => $parameters];
            if ('' !== $controller = trim((string) ($service['controller'] ?? ''))) {
                $catalogue['controller'] = $controller;
            }

            $durations = [];
            foreach ((array) ($service['cookie_durations'] ?? []) as $name => $days) {
                if (is_numeric($days)) {
                    $durations[(string) $name] = (int) $days;
                }
            }
            if ([] !== $durations) {
                ksort($durations);
                $catalogue['cookie_durations'] = $durations;
            }

            return $catalogue;
        }

        $declared = $base + [
            $isModule ? 'module' : 'custom' => true,
            'kind' => (string) ($service['kind'] ?? 'declarative'),
            'name' => (string) ($service['name'] ?? ''),
            'controller' => (string) ($service['controller'] ?? ''),
            'policy_url' => (string) ($service['policy_url'] ?? ''),
            'cookies' => array_values(array_map(static fn (array $cookie): array => array_filter([
                'name' => (string) $cookie['name'],
                'domain' => isset($cookie['domain']) ? (string) $cookie['domain'] : null,
                'duration_days' => isset($cookie['duration_days']) ? (int) $cookie['duration_days'] : null,
                'description' => isset($cookie['description']) ? (string) $cookie['description'] : null,
            ], static fn (mixed $value): bool => null !== $value), (array) ($service['cookies'] ?? []))),
        ];

        foreach (['script_url', 'code', 'justification'] as $optional) {
            if (isset($service[$optional]) && '' !== $service[$optional]) {
                $declared[$optional] = (string) $service[$optional];
            }
        }

        if ($isModule) {
            $declared['proposed'] = (bool) ($service['proposed'] ?? true);
            $declared['strictly_necessary'] = (bool) ($service['strictly_necessary'] ?? false);
        }

        return $declared;
    }
}
