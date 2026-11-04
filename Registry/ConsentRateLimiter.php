<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * A module cannot add a limiter to the framework configuration of the core: the factory is
 * built here, on the application cache. The threshold is wide on purpose (shops share an IP
 * behind a NAT); behind a proxy the client address needs `trusted_proxies`.
 */
final readonly class ConsentRateLimiter
{
    public const LIMIT = 120;

    private RateLimiterFactory $factory;

    public function __construct(
        #[Autowire(service: 'cache.app')]
        CacheItemPoolInterface $cache,
        int $limit = self::LIMIT,
    ) {
        $this->factory = new RateLimiterFactory(
            ['id' => 'ccm_consent', 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '1 minute'],
            new CacheStorage($cache),
        );
    }

    /** @return int|null seconds to wait when refused, null when accepted */
    public function consume(string $clientKey): ?int
    {
        $limit = $this->factory->create($clientKey)->consume();

        return $limit->isAccepted() ? null : max(1, $limit->getRetryAfter()->getTimestamp() - time());
    }
}
