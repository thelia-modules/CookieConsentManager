<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

use Thelia\Model\ConfigQuery;

class LastPurgeStore
{
    public const KEY = 'ccm_last_purge_at';

    public function read(): ?\DateTimeImmutable
    {
        $value = ConfigQuery::read(self::KEY);

        return null === $value || '' === $value ? null : new \DateTimeImmutable($value);
    }

    public function write(\DateTimeImmutable $at): void
    {
        ConfigQuery::write(self::KEY, $at->format(\DATE_ATOM), false, false);
    }
}
