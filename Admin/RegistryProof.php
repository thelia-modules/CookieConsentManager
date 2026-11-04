<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Model\ConsentEntry;
use CookieConsentManager\Publication\VersionRepository;

/** What a proof sheet holds: the entry, the version the visitor was served and its hashes (SPEC 6.4). */
final readonly class RegistryProof
{
    public function __construct(private VersionRepository $versions)
    {
    }

    /** @return array<string, mixed> */
    public function build(ConsentEntry $entry, ?string $uiLocale = null): array
    {
        $version = $this->versions->find($entry->getVersion());
        $snapshot = null === $version ? null : $this->versions->snapshot($version);
        $locale = substr($entry->getLocale(), 0, 2);

        return [
            'entry' => [
                'id' => $entry->getId(),
                'consent_id' => $entry->getConsentId(),
                'recorded_at' => StoredTime::describe($entry->getRecordedAt()),
                'action' => $entry->getAction(),
                'source' => $entry->getSource(),
                'locale' => $entry->getLocale(),
                'path' => $entry->getPath(),
                'browser' => $entry->getBrowser(),
                'purposes' => (array) json_decode($entry->getPurposesJson(), true),
                'services' => (array) json_decode($entry->getServicesJson(), true),
                'exempt' => (array) json_decode((string) $entry->getExemptJson(), true),
                'ip_truncated' => $entry->getIpTruncated(),
                'customer_id' => $entry->getCustomerId(),
            ],
            'version' => null === $version ? null : [
                'number' => $version->getVersion(),
                'created_at' => StoredTime::describe($version->getCreatedAt()),
                'author' => $version->getAuthor(),
                'module_version' => $version->getModuleVersion(),
                'config_hash' => $version->getConfigHash(),
                'js_hash' => $version->getJsHash(),
                'served_hash' => $version->getServedHash(),
                'substantial' => 1 === $version->getSubstantial(),
            ],
            'hash_matches' => null !== $version && $version->getServedHash() === $entry->getVersionHash(),
            'texts' => $this->shownTexts($snapshot, $locale),
            'layout' => $snapshot['config']['general']['layout'] ?? null,
            'policy_url' => null === $snapshot ? null : ($snapshot['config']['policy']['urls'][$locale] ?? $snapshot['config']['policy']['url'] ?? null),
            'purposes_labels' => null === $snapshot ? [] : $this->purposeLabels($snapshot['config']['purposes'], $uiLocale ?? $locale),
            'services_resolved' => $snapshot['services_resolved'] ?? [],
            'service_names' => array_column($snapshot['services_resolved'] ?? [], 'name', 'key'),
            'templates' => $snapshot['templates'] ?? [],
        ];
    }

    /**
     * The texts the visitor could see: the close link only exists when the close button was on.
     *
     * @param array<string, mixed>|null $snapshot
     *
     * @return array<string, string>
     */
    private function shownTexts(?array $snapshot, string $locale): array
    {
        $texts = $snapshot['config']['texts'][$locale] ?? $snapshot['config']['texts']['en'] ?? [];
        if (!($snapshot['config']['general']['close_button'] ?? false)) {
            unset($texts['close']);
        }

        return $texts;
    }

    /**
     * @param list<array<string, mixed>> $purposes
     *
     * @return array<string, string>
     */
    private function purposeLabels(array $purposes, string $locale): array
    {
        $labels = [];
        foreach ($purposes as $purpose) {
            $labels[$purpose['code']] = $purpose['label'][$locale] ?? $purpose['label']['en'] ?? $purpose['code'];
        }

        return $labels;
    }
}
