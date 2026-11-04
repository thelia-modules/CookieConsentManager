<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

/** The module stores every date as UTC wall-clock time: this states it, with the shop's local time next to it. */
final class StoredTime
{
    /** @return array{utc: string, local: string, zone: string}|null */
    public static function describe(?\DateTimeInterface $stored): ?array
    {
        if (null === $stored) {
            return null;
        }

        $utc = new \DateTimeImmutable($stored->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));
        $zone = new \DateTimeZone(date_default_timezone_get());

        return [
            'utc' => $utc->format('Y-m-d H:i:s').' UTC',
            'local' => $utc->setTimezone($zone)->format('Y-m-d H:i:s'),
            'zone' => $zone->getName(),
        ];
    }
}
