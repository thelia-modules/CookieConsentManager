<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

/** Writes the duration of a cookie, given in days, with the fixed labels of the front (months from 60 days, years from 730). */
final class CookieDuration
{
    /** @param array<string, string> $texts */
    public static function format(?int $days, array $texts, ?int $minutes = null): string
    {
        if (null !== $minutes && $minutes > 0) {
            return self::minutes($minutes, $texts);
        }

        if (null === $days || $days < 0) {
            return '';
        }

        if (0 === $days) {
            return $texts['duration_session'];
        }

        if ($days < 60) {
            return self::label($days, 'duration_day', 'duration_days', $texts);
        }

        if ($days < 730) {
            return self::label((int) round($days / 30.4), 'duration_month', 'duration_months', $texts);
        }

        return self::label((int) round($days / 365), 'duration_year', 'duration_years', $texts);
    }

    /** @param array<string, string> $texts */
    public static function months(int $count, array $texts): string
    {
        return self::label($count, 'duration_month', 'duration_months', $texts);
    }

    /** @param array<string, string> $texts */
    private static function minutes(int $minutes, array $texts): string
    {
        if ($minutes >= 1440) {
            return self::format(intdiv($minutes, 1440), $texts);
        }

        return self::label($minutes, 'duration_minute', 'duration_minutes', $texts);
    }

    /** @param array<string, string> $texts */
    private static function label(int $count, string $one, string $many, array $texts): string
    {
        return 1 === $count ? $texts[$one] : str_replace('%count%', (string) $count, $texts[$many]);
    }
}
