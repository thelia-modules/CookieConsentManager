<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

final class IpTruncator
{
    /** IPv4 to /24, IPv6 to /48. */
    public static function truncate(?string $ip): ?string
    {
        if (null === $ip || false === ($packed = @inet_pton($ip))) {
            return null;
        }

        $kept = 4 === \strlen($packed) ? 3 : 6;
        $masked = substr($packed, 0, $kept).str_repeat("\0", \strlen($packed) - $kept);

        return (string) inet_ntop($masked);
    }
}
