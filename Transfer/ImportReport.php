<?php

declare(strict_types=1);

namespace CookieConsentManager\Transfer;

use CookieConsentManager\Validation\ValidationIssue;

final readonly class ImportReport
{
    /**
     * @param list<string>          $environmentPending fields to confirm before publishing
     * @param list<string>          $skippedServices    experimental services dropped because the setting is off
     * @param list<ValidationIssue> $issues
     */
    public function __construct(
        public array $environmentPending,
        public array $skippedServices,
        public ?bool $policyResolved,
        public array $issues,
    ) {
    }
}
