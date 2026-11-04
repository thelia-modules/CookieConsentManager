<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

/**
 * The page where a choice was made, minus what could act as a secret: the registry is read by
 * administrators and exported, so a token carried by the address (guest order tracking since Thelia 3.3)
 * must never reach it.
 */
final class RecordedPath
{
    private const MASK = '*';

    /** Routes whose remaining segments are a secret, with or without a language prefix. */
    private const SECRET_ROUTES = [['order', 'track'], ['password', 'reset']];

    private const OPAQUE_TOKEN = '/^(?:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|(?=[A-Za-z_]*\d)[A-Za-z0-9_]{24,})$/i';

    /** Thelia token `<id>.<expires>.<hex signature>`: any segment holding a long run of hexadecimal characters. */
    private const HEX_RUN = '/[0-9a-f]{24,}/i';

    public static function mask(string $path): string
    {
        $segments = explode('/', $path);
        $masked = [];
        foreach ($segments as $index => $segment) {
            if ($index > 0 && self::startsSecretRoute($masked)) {
                $masked[] = self::MASK;
                break;
            }
            $masked[] = 1 === preg_match(self::OPAQUE_TOKEN, $segment) || 1 === preg_match(self::HEX_RUN, $segment) ? self::MASK : $segment;
        }

        return implode('/', $masked);
    }

    /** @param list<string> $segments the segments kept so far */
    private static function startsSecretRoute(array $segments): bool
    {
        foreach (self::SECRET_ROUTES as $route) {
            if ($route === \array_slice($segments, -\count($route))) {
                return true;
            }
        }

        return false;
    }
}
