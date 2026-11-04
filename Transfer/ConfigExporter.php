<?php

declare(strict_types=1);

namespace CookieConsentManager\Transfer;

use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Publication\ModuleVersion;
use CookieConsentManager\Publication\VersionRepository;

final readonly class ConfigExporter
{
    public const SCHEMA = 'ccm-config/1';
    public const ENVIRONMENT_PATHS = ['services[*].parameters', 'policy.url', 'consent_mode.regions'];

    public function __construct(
        private DraftRepository $drafts,
        private CatalogueReader $catalogue,
        private ModuleVersion $moduleVersion,
        private VersionRepository $versions,
    ) {
    }

    /** @return array<string, mixed> */
    public function export(): array
    {
        return $this->fromDraft($this->drafts->load(), $this->versions->latest()?->getVersion());
    }

    /**
     * @param array<string, mixed> $draft normalized draft
     *
     * @return array<string, mixed>
     */
    public function fromDraft(array $draft, ?int $sourceVersion): array
    {
        unset($draft['_meta']);

        $draft['services'] = array_map(function (array $service): array {
            if (isset($service['custom']) || isset($service['module'])) {
                return $service;
            }

            $service['parameters'] = (object) $service['parameters'];

            return ['key' => $service['key'], 'verified' => $this->catalogue->find($service['key'])->verified ?? false] + $service;
        }, $draft['services']);
        $draft['presets'] = (object) ($draft['presets'] ?? []);
        $draft['policy']['urls'] = (object) ($draft['policy']['urls'] ?? []);
        $draft['consent_mode']['regions'] = array_map(static function (array $region): array {
            $region['default'] = (object) ($region['default'] ?? []);

            return $region;
        }, $draft['consent_mode']['regions']);
        foreach ($draft['purposes'] as $index => $purpose) {
            $draft['purposes'][$index]['label'] = (object) $purpose['label'];
            $draft['purposes'][$index]['description'] = (object) $purpose['description'];
        }

        return [
            'schema' => self::SCHEMA,
            'exported_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'module_version' => $this->moduleVersion->current(),
            'source_version' => $sourceVersion,
            'environment' => self::ENVIRONMENT_PATHS,
        ] + $draft;
    }

    /** @param array<string, mixed> $document */
    public static function toJson(array $document): string
    {
        return json_encode($document, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION)."\n";
    }
}
