<?php

declare(strict_types=1);

namespace CookieConsentManager\Validation;

/** Environment fields imported from another site and not confirmed yet. */
final readonly class EnvironmentSection implements SectionValidator
{
    public function __construct(
        private IssueFactory $issues,
    ) {
    }

    public static function priority(): int
    {
        return 600;
    }

    public function validate(array $draft): array
    {
        $issues = [];
        foreach ((array) ($draft['_meta']['environment_pending'] ?? []) as $path) {
            $issues[] = $this->issues->error('environment_unconfirmed', (string) $path, 'The environment field "%path%" imported from another site is not confirmed.', ['%path%' => $path]);
        }

        return $issues;
    }
}
