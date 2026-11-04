<?php

declare(strict_types=1);

namespace CookieConsentManager\Validation;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Validates one section of the normalized draft. Any autoconfigured service implementing it is picked up by
 * DraftValidator; the issues of the sections are concatenated by decreasing priority.
 */
#[AutoconfigureTag(self::TAG)]
interface SectionValidator
{
    public const TAG = 'ccm.section_validator';

    /** Used when the tag carries no explicit priority: higher runs first. */
    public static function priority(): int;

    /**
     * @param array<string, mixed> $draft normalized draft
     *
     * @return list<ValidationIssue>
     */
    public function validate(array $draft): array;
}
