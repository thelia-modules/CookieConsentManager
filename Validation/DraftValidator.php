<?php

declare(strict_types=1);

namespace CookieConsentManager\Validation;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Runs the section validators (tag `ccm.section_validator`, see SectionValidator) and concatenates their issues. */
final readonly class DraftValidator
{
    public const MAX_COOKIE_DAYS = 7300;
    public const FRANCE_PRESET = 'fr-FR';

    public const KEY_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';
    public const MAX_RETENTION_MONTHS = 60;
    public const TEXT_LIMITS = ['title' => 120, 'body' => 1500, 'accept' => 60, 'reject' => 60, 'customize' => 60, 'close' => 80, 'allow' => 60, 'preferences' => 60, 'free_text' => 1500];
    public const REQUIRED_TEXTS = ['accept', 'reject', 'customize'];

    public const DISCONTINUED = 'universal-analytics';

    /** Issues that refuse an import outright: an address that is not a path of the shop or an https URL is never stored. */
    public const URL_ERROR_CODES = ['policy_url_invalid', 'script_url'];

    /** Issues that refuse an import outright: a service claiming to come from a module that does not declare it, or going beyond its declaration. */
    public const MODULE_ERROR_CODES = ['module_undeclared', 'module_code', 'module_script_url', 'module_necessary'];

    /** @param iterable<SectionValidator> $sections */
    public function __construct(
        #[AutowireIterator(SectionValidator::TAG, defaultPriorityMethod: 'priority')]
        private iterable $sections,
    ) {
    }

    /**
     * @param array<string, mixed> $draft normalized draft
     *
     * @return list<ValidationIssue>
     */
    public function validate(array $draft): array
    {
        $issues = [];
        foreach ($this->sections as $section) {
            array_push($issues, ...$section->validate($draft));
        }

        return $issues;
    }

    /** @param list<ValidationIssue> $issues */
    public static function hasErrors(array $issues): bool
    {
        foreach ($issues as $issue) {
            if ($issue->isError()) {
                return true;
            }
        }

        return false;
    }

    public static function isPolicyUrl(string $url): bool
    {
        if (str_contains($url, '\\')) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return !str_starts_with($url, '//') && 1 !== preg_match('/\s/', $url);
        }

        return 1 === preg_match('~^https://[^\s"\'<>/]+[^\s"\'<>]*$~i', $url);
    }
}
