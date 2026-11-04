<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin\AlertProvider;

use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Translation of the dashboard alerts, shared by the providers. */
final readonly class AlertText
{
    public const DOMAIN = 'cookieconsentmanager.bo.default-twig';

    public function __construct(private TranslatorInterface $translator)
    {
    }

    /** @param array<string, string> $parameters */
    public function t(string $message, array $parameters = []): string
    {
        return $this->translator->trans($message, $parameters, self::DOMAIN);
    }

    public function locale(): string
    {
        return $this->translator instanceof LocaleAwareInterface ? substr($this->translator->getLocale(), 0, 2) : 'en';
    }
}
