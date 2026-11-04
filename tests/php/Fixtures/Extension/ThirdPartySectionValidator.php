<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Fixtures\Extension;

use CookieConsentManager\Validation\SectionValidator;
use CookieConsentManager\Validation\ValidationIssue;

final class ThirdPartySectionValidator implements SectionValidator
{
    public static function priority(): int
    {
        return 0;
    }

    public function validate(array $draft): array
    {
        return [new ValidationIssue(ValidationIssue::ERROR, 'third_party_rule', 'general.layout', 'refused by a third-party section')];
    }
}
