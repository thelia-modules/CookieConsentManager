<?php

declare(strict_types=1);

namespace CookieConsentManager\Validation;

/** Whether an address looks like the page of terms of sale or legal notice instead of a page about cookies. */
final class TermsPageUrl
{
    private const TERMS = ['cgv', 'cgu', 'cgvu', 'terms', 'conditions', 'mentions', 'legales', 'legale', 'legal'];
    private const COOKIES = ['cookie', 'cookies', 'traceur', 'traceurs'];

    public static function matches(string $url): bool
    {
        $path = (string) (parse_url($url, \PHP_URL_PATH) ?? $url);
        $tokens = preg_split('/[^a-z0-9]+/', strtolower($path), -1, \PREG_SPLIT_NO_EMPTY) ?: [];

        return [] !== array_intersect($tokens, self::TERMS) && [] === array_intersect($tokens, self::COOKIES);
    }
}
