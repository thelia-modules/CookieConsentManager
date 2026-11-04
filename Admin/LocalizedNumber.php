<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

/** A number written the way the back-office language writes it (decimal comma in French). */
final class LocalizedNumber
{
    public static function format(float|int|null $value, string $locale, int $decimals = 1): string
    {
        if (null === $value) {
            return '-';
        }

        return 'fr' === substr($locale, 0, 2)
            ? number_format($value, $decimals, ',', "\u{202F}")
            : number_format($value, $decimals, '.', ',');
    }
}
