<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Validation\ValidationIssue;

final readonly class SaveResult
{
    /**
     * @param array<string, mixed>  $draft    the draft as it stands after the attempt: saved, or the refused candidate
     * @param list<ValidationIssue> $issues   issues of the saved section only
     * @param list<ContrastFinding> $contrast
     */
    public function __construct(
        public bool $saved,
        public array $draft,
        public array $issues,
        public array $contrast = [],
    ) {
    }
}
