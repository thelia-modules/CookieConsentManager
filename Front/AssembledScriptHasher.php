<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use CookieConsentManager\Publication\ServedScriptHasher;

/** Hashes the very bytes the visitor is served. */
final readonly class AssembledScriptHasher implements ServedScriptHasher
{
    public function __construct(private ServedScriptAssembler $assembler)
    {
    }

    public function hash(array $snapshot): string
    {
        return hash('sha256', $this->assembler->assemble($snapshot));
    }
}
