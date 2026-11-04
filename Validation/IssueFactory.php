<?php

declare(strict_types=1);

namespace CookieConsentManager\Validation;

use CookieConsentManager\CookieConsentManager;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Translated issues, shared by the section validators. */
final readonly class IssueFactory
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    /** @param array<string, string> $parameters */
    public function error(string $code, string $field, string $message, array $parameters = []): ValidationIssue
    {
        return new ValidationIssue(ValidationIssue::ERROR, $code, $field, $this->trans($message, $parameters));
    }

    /** @param array<string, string> $parameters */
    public function trans(string $message, array $parameters = []): string
    {
        return $this->translator->trans($message, $parameters, CookieConsentManager::DOMAIN_NAME);
    }

    public function locale(): string
    {
        return $this->translator instanceof LocaleAwareInterface ? substr($this->translator->getLocale(), 0, 2) : 'en';
    }
}
