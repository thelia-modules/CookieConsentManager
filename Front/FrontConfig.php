<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

/** What the page of one request shows: the inline configuration and the snapshot it comes from. */
final readonly class FrontConfig
{
    /**
     * @param array<string, mixed> $config   inline configuration (CONTRATS.md section 3)
     * @param array<string, mixed> $snapshot
     */
    public function __construct(
        public array $config,
        public array $snapshot,
    ) {
    }

    public function mode(): string
    {
        return $this->config['mode'];
    }

    public function isLive(): bool
    {
        return 'live' === $this->config['mode'];
    }
}
