<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

/**
 * The shared cache of Thelia exists from 3.3 only: before, there is nothing to purge.
 * The invalidator is injected by CookieConsentManager::configureServices(), as a reference that is null when the class does not exist.
 */
class SharedCachePurger
{
    public function __construct(
        private readonly ?object $invalidator = null,
    ) {
    }

    public function purgeAll(): bool
    {
        if (null === $this->invalidator || !class_exists('Thelia\Core\SharedCache\SharedCacheKey')) {
            return false;
        }

        $this->invalidator->purge(['Thelia\Core\SharedCache\SharedCacheKey'::ALL]);

        return true;
    }
}
