<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

use CookieConsentManager\Validation\ValidationIssue;

final class PublicationRefusedException extends \RuntimeException
{
    /** @param list<ValidationIssue> $issues */
    public function __construct(public readonly array $issues)
    {
        parent::__construct(implode("\n", array_map(
            static fn (ValidationIssue $issue): string => $issue->field.': '.$issue->message,
            array_filter($issues, static fn (ValidationIssue $issue): bool => $issue->isError()),
        )));
    }
}
