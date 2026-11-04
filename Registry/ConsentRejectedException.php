<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

final class ConsentRejectedException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $error,
        public readonly ?string $field = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($error);
    }
}
