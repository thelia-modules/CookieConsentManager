<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Number of the latest published version, kept in the application cache so that a page view does
 * not query for it. The publication is the only writer of a version: it calls `invalidate()` once
 * the version is committed. The lifetime bounds the two cases the publication cannot see (a request
 * that computed the pointer just before a commit, a database restored behind the module's back).
 */
final readonly class PublishedVersionPointer
{
    private const KEY = 'ccm.published_pointer';
    public const LIFETIME = 300;

    public function __construct(
        private VersionRepository $versions,
        #[Autowire(service: 'cache.app')]
        private CacheInterface $cache,
    ) {
    }

    /** @return int 0 while nothing is published */
    public function latest(): int
    {
        return $this->cache->get(self::KEY, function ($item): int {
            $item->expiresAfter(self::LIFETIME);

            return $this->versions->latestNumber();
        });
    }

    public function invalidate(): void
    {
        $this->cache->delete(self::KEY);
    }
}
