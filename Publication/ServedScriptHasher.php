<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

/**
 * Hash (SHA-256, hexadecimal) of the JavaScript that would be served for a snapshot.
 * AssembledScriptHasher, bound in CookieConsentManager.php, hashes the very bytes of the served script.
 */
interface ServedScriptHasher
{
    /** @param array<string, mixed> $snapshot */
    public function hash(array $snapshot): string;
}
