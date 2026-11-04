<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

use CookieConsentManager\Draft\DefaultSettings;

/**
 * Builds the configuration published inline in the head (shape in docs/runtime-contracts.md, "Published configuration") from a
 * snapshot only: the same snapshot gives the same JSON for a given language. The URLs it carries
 * are the root paths of the routes: the front replaces them at render time by the ones the router
 * generates for the current request (base path of the shop).
 */
final readonly class PublishedConfigBuilder
{
    public const ENDPOINT = '/ccm/consent';
    public const PREFERENCES = '/ccm/preferences';

    /**
     * @param array<string, mixed> $snapshot
     *
     * @return array<string, mixed>
     */
    public function build(array $snapshot, string $locale, int|string $version, string $hash, int $substantialVersion, string $mode = 'live'): array
    {
        $config = $snapshot['config'];
        $resolved = $snapshot['services_resolved'];
        $exemptKeys = array_column($config['exempt_measurement'], 'service');

        $consentMode = $config['consent_mode'];
        $services = [];
        $exempt = [];
        foreach ($resolved as $service) {
            if (\in_array($service['key'], $exemptKeys, true)) {
                $exempt[] = ['key' => $service['key'], 'user' => (object) $service['user'], 'cookies' => $service['cookies']];
                continue;
            }
            $services[] = $this->service($service);
        }

        return [
            'format' => 1,
            'version' => $version,
            'hash' => $hash,
            'substantialVersion' => $substantialVersion,
            'locale' => $locale,
            'mode' => $mode,
            'endpoint' => self::ENDPOINT,
            'script' => \sprintf('/ccm/%s-%s.js', $version, $hash),
            'preferencesUrl' => self::PREFERENCES,
            'repromptDays' => intdiv(min($config['general']['renewal_months'], 13) * 365, 12),
            'options' => [
                'layout' => $config['general']['layout'],
                'closeButton' => $config['general']['close_button'],
                'floatingButton' => $config['general']['floating_button'],
                'reloadOnWithdraw' => $config['general']['reload_on_script_withdraw'],
                'blockEmbeds' => $config['general']['block_embedded_iframes'],
                'experimental' => $config['general']['experimental_services'],
            ],
            'gcm' => [
                'enabled' => $consentMode['enabled'],
                'mode' => $consentMode['mode'],
                'defaults' => $this->defaults($consentMode),
                'waitForUpdate' => 'advanced' === $consentMode['mode'] ? $consentMode['wait_for_update_ms'] : 0,
                'adsDataRedaction' => $consentMode['ads_data_redaction'],
                'urlPassthrough' => $consentMode['url_passthrough'],
                'uet' => $consentMode['uet']['enabled'],
            ],
            'purposes' => $this->purposes($config['purposes'], $resolved),
            'services' => $services,
            'exempt' => $exempt,
        ];
    }

    /**
     * @param array<string, mixed> $consentMode
     *
     * @return list<array<string, mixed>>
     */
    private function defaults(array $consentMode): array
    {
        $base = array_fill_keys(DefaultSettings::SIGNALS, 'denied');
        $base['security_storage'] = 'granted';

        $defaults = [];
        if ('advanced' === $consentMode['mode']) {
            foreach ($consentMode['regions'] as $region) {
                $defaults[] = ['region' => array_values($region['iso'])] + array_replace($base, (array) ($region['default'] ?? []));
            }
        }
        $defaults[] = ['region' => []] + $base;

        return $defaults;
    }

    /**
     * @param list<array<string, mixed>> $purposes
     * @param list<array<string, mixed>> $resolved
     *
     * @return list<array{code: string, signals: list<string>}>
     */
    private function purposes(array $purposes, array $resolved): array
    {
        $active = array_unique(array_column($resolved, 'purpose'));
        $result = [];

        foreach ($purposes as $purpose) {
            if (\in_array($purpose['code'], $active, true)) {
                $result[] = ['code' => $purpose['code'], 'signals' => $purpose['consent_mode']];
            }
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
        $published = [
            'key' => $service['key'],
            'purpose' => $service['purpose'],
            'kind' => $service['kind'],
            'googleTag' => $service['google_tag'],
            'user' => (object) $service['user'],
            'cookies' => $service['cookies'],
            'storage' => $service['storage'],
        ];

        if ('script' !== $service['kind'] || isset($service['script_url'])) {
            $published['name'] = $service['name'];
        }

        return $published;
    }
}
