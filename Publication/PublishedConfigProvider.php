<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

use CookieConsentManager\Model\Version;

/** Inline configuration of a published version for one language (what the head renders). */
final readonly class PublishedConfigProvider
{
    public function __construct(
        private VersionRepository $versions,
        private PublishedConfigBuilder $builder,
    ) {
    }

    /** @return array<string, mixed> */
    public function forVersion(Version $version, string $locale): array
    {
        return $this->builder->build(
            $this->versions->snapshot($version),
            $locale,
            $version->getVersion(),
            $version->getServedHash(),
            $this->versions->substantialVersionUpTo($version->getVersion()),
        );
    }

    /** @return array<string, mixed>|null null while nothing is published */
    public function latest(string $locale): ?array
    {
        $version = $this->versions->latest();

        return null === $version ? null : $this->forVersion($version, $locale);
    }
}
