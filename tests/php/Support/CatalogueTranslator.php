<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Support;

use Symfony\Contracts\Translation\TranslatorInterface;

/** Reads the catalogue files of the module directly, with the fallback of the Thelia translator (the message itself). */
final class CatalogueTranslator implements TranslatorInterface
{
    private const DIRECTORIES = [
        'cookieconsentmanager' => 'I18n',
        'cookieconsentmanager.fo.flexy' => 'I18n/frontOffice/flexy',
        'cookieconsentmanager.bo.default-twig' => 'I18n/backOffice/default-twig',
    ];

    /** @var array<string, array<string, string>> */
    private array $catalogues = [];

    public function __construct(private string $locale = 'en_US')
    {
    }

    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $locale ??= $this->locale;
        $key = $domain.'|'.$locale;
        if (!isset($this->catalogues[$key])) {
            $file = \dirname(__DIR__, 3).'/'.(self::DIRECTORIES[(string) $domain] ?? 'I18n').'/'.$locale.'.php';
            $this->catalogues[$key] = is_file($file) ? require $file : [];
        }

        return strtr($this->catalogues[$key][$id] ?? $id, $parameters);
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
