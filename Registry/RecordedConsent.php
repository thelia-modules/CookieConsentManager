<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

final readonly class RecordedConsent
{
    public function __construct(
        public string $consentId,
        public \DateTimeImmutable $recordedAt,
        public ConsentCookie $cookie,
    ) {
    }
}
