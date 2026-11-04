<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

final class IpTruncator
{
    /** IPv4 to /24, IPv6 to /48; an IPv4-mapped IPv6 address (dual-stack server) is an IPv4 one. */
    public static function truncate(?string $ip): ?string
    {
        if (null === $ip || false === ($packed = @inet_pton($ip))) {
            return null;
        }
        if (16 === \strlen($packed) && str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            $packed = substr($packed, 12);
        }

        $kept = 4 === \strlen($packed) ? 3 : 6;
        $masked = substr($packed, 0, $kept).str_repeat("\0", \strlen($packed) - $kept);

        return (string) inet_ntop($masked);
    }
}
