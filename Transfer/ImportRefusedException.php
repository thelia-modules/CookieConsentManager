<?php

declare(strict_types=1);

namespace CookieConsentManager\Transfer;

final class ImportRefusedException extends \RuntimeException
{
    /** @param list<string> $violations */
    public function __construct(public readonly array $violations)
    {
        parent::__construct(implode("\n", $violations));
    }
}
