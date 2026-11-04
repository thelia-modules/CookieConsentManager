<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

final class CanonicalJson
{
    /** @param array<mixed> $value */
    public static function encode(array $value): string
    {
        return json_encode(self::sort($value), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);
    }

    /** @param array<mixed> $value */
    public static function hash(array $value): string
    {
        return hash('sha256', self::encode($value));
    }

    /**
     * @param array<mixed> $value
     *
     * @return array<mixed>
     */
    private static function sort(array $value): array
    {
        foreach ($value as $key => $item) {
            if (\is_array($item)) {
                $value[$key] = self::sort($item);
            }
        }

        if (!array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
