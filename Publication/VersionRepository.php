<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

use CookieConsentManager\Model\Version;
use CookieConsentManager\Model\VersionQuery;

final readonly class VersionRepository
{
    public function __construct(private SnapshotNormalizer $normalizer = new SnapshotNormalizer())
    {
    }

    public function latest(): ?Version
    {
        return VersionQuery::create()->orderByVersion('DESC')->findOne();
    }

    /** Number of the latest version without reading its snapshot, 0 while nothing is published. */
    public function latestNumber(): int
    {
        return (int) VersionQuery::create()->orderByVersion('DESC')->select('Version')->findOne();
    }

    public function find(int $version): ?Version
    {
        return VersionQuery::create()->filterByVersion($version)->findOne();
    }

    /** @return list<Version> */
    public function all(): array
    {
        return array_values(iterator_to_array(VersionQuery::create()->orderByVersion('DESC')->find(), false));
    }

    public function substantialVersionUpTo(int $version): int
    {
        return VersionQuery::create()
            ->filterBySubstantial(1)
            ->filterByVersion($version, \Propel\Runtime\ActiveQuery\Criteria::LESS_EQUAL)
            ->orderByVersion('DESC')
            ->findOne()?->getVersion() ?? 0;
    }

    public function nextNumber(): int
    {
        return ($this->latest()?->getVersion() ?? 0) + 1;
    }

    /** @return array<string, mixed> */
    public function snapshot(Version $version): array
    {
        return $this->normalizer->normalize((array) json_decode($version->getSnapshot(), true, 512, \JSON_THROW_ON_ERROR));
    }
}
