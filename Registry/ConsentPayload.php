<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

final readonly class ConsentPayload
{
    /**
     * @param array<string, int> $purposes
     * @param array<string, int> $services
     * @param array<string, int> $exempt
     */
    public function __construct(
        public ?string $consentId,
        public string $action,
        public string $source,
        public int $configVersion,
        public string $servedHash,
        public string $locale,
        public string $path,
        public array $purposes,
        public array $services,
        public array $exempt,
    ) {
    }
}
