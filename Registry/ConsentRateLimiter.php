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
        $limit = $this->factory->create(self::counterKey($clientKey))->consume();

        return $limit->isAccepted() ? null : max(1, $limit->getRetryAfter()->getTimestamp() - time());
    }

    /** A visitor owns a whole IPv6 /64: rotating inside it must not give a fresh counter. IPv4 is kept as is. */
    private static function counterKey(string $clientKey): string
    {
        $packed = @inet_pton($clientKey);
        if (false === $packed || 16 !== \strlen($packed)) {
            return $clientKey;
        }
        if (str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return (string) inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8)).'/64';
    }
}
