<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Support;

use CookieConsentManager\Publication\SharedCachePurger;

final class RecordingCachePurger extends SharedCachePurger
{
    public int $purges = 0;

    public function purgeAll(): bool
    {
        ++$this->purges;

        return true;
    }
}
