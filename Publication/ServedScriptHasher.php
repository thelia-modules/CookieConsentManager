<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

/**
 * Hash (SHA-256, hexadecimal) of the JavaScript that would be served for a snapshot.
 * The front lot provides the real implementation, which hashes the very bytes of the
 * served script; the one bound by default is provisional.
 */
interface ServedScriptHasher
{
    /** @param array<string, mixed> $snapshot */
    public function hash(array $snapshot): string;
}
