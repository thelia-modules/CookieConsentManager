<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\CodeLabels;
use CookieConsentManager\Admin\CsvLabeler;
use CookieConsentManager\Admin\FieldLabeler;
use CookieConsentManager\Validation\ExemptionWhitelist;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BackOfficeTranslationsTest extends TestCase
{
    private const ROOT = __DIR__.'/../../..';
    private const LANGUAGES = ['fr_FR', 'en_US', 'de_DE', 'nl_NL', 'es_ES'];

    /** @return iterable<string, array{string}> */
    public static function languages(): iterable
    {
        foreach (self::LANGUAGES as $language) {
            yield $language => [$language];
        }
    }

    #[DataProvider('languages')]
    public function testEveryEnglishTextOfTheBackOfficeHasATranslationInEveryLanguage(string $language): void
    {
        $catalogue = require self::ROOT.'/I18n/backOffice/default-twig/'.$language.'.php';
        $missing = array_values(array_filter(array_keys(self::usedTexts()), static fn (string $text): bool => !isset($catalogue[$text])));

        self::assertSame([], $missing, 'untranslated back-office texts in '.$language);
    }

    #[DataProvider('languages')]
    public function testTheCatalogueOfALanguageHasTheKeysAndPlaceholdersOfEnglish(string $language): void
    {
        $english = self::catalogue('en_US');
        $catalogue = self::catalogue($language);

        self::assertSame([], array_values(array_diff(array_keys($english), array_keys($catalogue))), 'keys missing in '.$language);
        self::assertSame([], array_values(array_diff(array_keys($catalogue), array_keys($english))), 'keys unknown to English in '.$language);
        foreach ($english as $text => $unused) {
            self::assertSame(self::placeholders($text), self::placeholders($catalogue[$text] ?? ''), 'placeholders of "'.$text.'" in '.$language);
            self::assertNotSame('', trim($catalogue[$text] ?? ''), 'empty translation of "'.$text.'" in '.$language);
        }
    }

    /** @return array<string, string> */
    private static function catalogue(string $language): array
    {
        return require self::ROOT.'/I18n/backOffice/default-twig/'.$language.'.php';
    }

    /** @return list<string> */
    private static function placeholders(string $text): array
    {
        preg_match_all('/%[A-Za-z_]+%|%d|%s/', $text, $matches);
        sort($matches[0]);

        return $matches[0];
    }

    /** @return array<string, true> */
    private static function usedTexts(): array
    {
        $texts = [];
        foreach (CodeLabels::GROUPS as $labels) {
            foreach ($labels as $label) {
                $texts[$label] = true;
            }
        }

        foreach ([FieldLabeler::class, CsvLabeler::class] as $class) {
            foreach ((new \ReflectionClass($class))->getConstants() as $name => $labels) {
                if ('DOMAIN' !== $name && \is_array($labels)) {
                    foreach ($labels as $label) {
                        $texts[$label] = true;
                    }
                }
            }
        }
        foreach (ExemptionWhitelist::SERVICES as $label) {
            $texts[$label] = true;
        }

        $files = new \RegexIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT.'/templates/backOffice')), '/\.twig$/');
        foreach ($files as $file) {
            $source = (string) file_get_contents($file->getPathname());
            preg_match_all('/\'((?:[^\'\\\\]|\\\\.)*)\'\s*\|\s*trans\b/', $source, $matches);
            foreach ($matches[1] as $text) {
                $texts[str_replace("\\'", "'", $text)] = true;
            }
        }

        foreach (['Admin', 'Controller/Admin', 'Command', 'Validation', 'Transfer', 'Publication'] as $directory) {
            $php = new \RegexIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT.'/'.$directory)), '/\.php$/');
            foreach ($php as $file) {
                preg_match_all('/(?:->t|->say)\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', (string) file_get_contents($file->getPathname()), $matches);
                foreach ($matches[1] as $text) {
                    $texts[str_replace("\\'", "'", $text)] = true;
                }
            }
        }

        return $texts;
    }
}
