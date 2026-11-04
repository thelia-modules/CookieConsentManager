<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Catalogue\CookieDurations;
use CookieConsentManager\Draft\ServiceControllers;

/**
 * The snapshot freezes everything a visitor depends on: the configuration and the
 * catalogue data of each active service, so that a later catalogue update never
 * rewrites a published version.
 */
final readonly class SnapshotAssembler
{
    public const SCHEMA = 'ccm-snapshot/1';

    private ServiceControllers $controllers;
    private CookieDurations $durations;

    public function __construct(
        private CatalogueReader $catalogue,
        private ModuleVersion $moduleVersion,
        private OverriddenTemplatesProvider $templates,
        ?ServiceControllers $controllers = null,
        ?CookieDurations $durations = null,
    ) {
        $this->controllers = $controllers ?? new ServiceControllers(__DIR__.'/../Resources/catalogue/controllers.json');
        $this->durations = $durations ?? new CookieDurations(__DIR__.'/../Resources/catalogue/cookie-durations.json');
    }

    /**
     * @param array<string, mixed> $draft normalized draft
     *
     * @return array<string, mixed>
     */
    public function assemble(array $draft): array
    {
        unset($draft['_meta']);

        $resolved = [];
        foreach ($draft['services'] as $service) {
            if ($service['enabled']) {
                $resolved[] = $this->resolve($service);
            }
        }

        return [
            'schema' => self::SCHEMA,
            'module_version' => $this->moduleVersion->current(),
            'config' => $draft,
            'services_resolved' => $resolved,
            'templates' => $this->templates->list(),
        ];
    }

    /**
     * @param array<string, mixed> $service
     *
     * @return array<string, mixed>
     */
    private function resolve(array $service): array
    {
        if (!isset($service['custom']) && !isset($service['module'])) {
            $entry = $this->catalogue->find($service['key']);
            $details = $this->durations->details($service['key'], $entry->cookies ?? [], $service['cookie_durations'] ?? []);

            return [
                'key' => $service['key'],
                'purpose' => $service['purpose'],
                'kind' => $entry->kind ?? 'script',
                'google_tag' => $entry->googleTag ?? false,
                'name' => $entry->name ?? $service['key'],
                'controller' => '' !== ($controller = $this->controllers->effective($service)) ? $controller : ($entry->name ?? $service['key']),
                'policy_url' => $entry->uri ?? '',
                'user' => $service['parameters'],
                'cookies' => $entry->cookies ?? [],
                'storage' => $entry->storage ?? [],
                'domains' => $entry->domains ?? [],
            ] + ([] === $details ? [] : ['cookie_details' => $details]);
        }

        $resolved = [
            'key' => $service['key'],
            'purpose' => $service['purpose'],
            'kind' => $service['kind'],
            'google_tag' => false,
            'name' => $service['name'],
            'controller' => $service['controller'],
            'policy_url' => $service['policy_url'],
            'user' => [],
            'cookies' => array_column($service['cookies'], 'name'),
            'storage' => [],
            'domains' => [],
            'cookie_details' => $service['cookies'],
            'strictly_necessary' => $service['strictly_necessary'] ?? false,
        ];

        if ('custom' === $service['kind']) {
            $resolved['code'] = $service['code'] ?? '';
        } elseif ('script' === $service['kind']) {
            $resolved['script_url'] = $service['script_url'] ?? '';
            $resolved['code'] = \sprintf(
                'var s=document.createElement("script");s.async=true;s.src=%s;document.head.appendChild(s);',
                json_encode($service['script_url'] ?? '', \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG),
            );
        }

        return $resolved;
    }
}
