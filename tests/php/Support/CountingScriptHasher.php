<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Support;

use CookieConsentManager\Publication\ServedScriptHasher;

final class CountingScriptHasher implements ServedScriptHasher
{
    public int $calls = 0;

    public function __construct(private readonly ServedScriptHasher $inner)
    {
    }

    public function hash(array $snapshot): string
    {
        ++$this->calls;

        return $this->inner->hash($snapshot);
    }
}
