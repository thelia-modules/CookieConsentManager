<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Front\FrontTexts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The module domain (validation messages) and the front domain: same keys and placeholders in the five languages. */
final class TranslationCatalogueTest extends TestCase
{
    private const ROOT = __DIR__.'/../../..';
    private const DOMAINS = ['I18n' => 'validation', 'I18n/frontOffice/flexy' => 'front'];
    private const LANGUAGES = ['fr_FR', 'en_US', 'de_DE', 'nl_NL', 'es_ES'];

    /** @return iterable<string, array{string, string}> */
    public static function catalogues(): iterable
    {
        foreach (self::DOMAINS as $directory => $name) {
            foreach (self::LANGUAGES as $language) {
                yield $name.' '.$language => [$directory, $language];
            }
        }
    }

    #[DataProvider('catalogues')]
    public function testACatalogueHasTheKeysAndPlaceholdersOfEnglish(string $directory, string $language): void
    {
        $english = self::catalogue($directory, 'en_US');
        $catalogue = self::catalogue($directory, $language);

        self::assertSame([], array_values(array_diff(array_keys($english), array_keys($catalogue))), 'keys missing');
        self::assertSame([], array_values(array_diff(array_keys($catalogue), array_keys($english))), 'keys unknown to English');
        foreach ($english as $message => $unused) {
            self::assertNotSame('', trim($catalogue[$message] ?? ''), 'empty translation of "'.$message.'"');
            self::assertSame(self::placeholders($message), self::placeholders($catalogue[$message] ?? ''), 'placeholders of "'.$message.'"');
        }
    }

    /** @return iterable<string, array{string}> */
    public static function languages(): iterable
    {
        foreach (self::LANGUAGES as $language) {
            yield $language => [$language];
        }
    }

    #[DataProvider('languages')]
    public function testEveryFrontMessageOfTheCodeIsInTheCatalogue(string $language): void
    {
        $directory = 'I18n/frontOffice/flexy';

        $reflection = new \ReflectionClass(FrontTexts::class);
        $used = [];
        foreach (['LABELS', 'MESSAGES', 'DEFAULT_TEXTS', 'POLICY'] as $constant) {
            array_push($used, ...array_values((array) $reflection->getConstant($constant)));
        }
        foreach (FrontTexts::DEFAULT_PURPOSES as $purpose) {
            array_push($used, ...array_values($purpose));
        }

        $catalogue = self::catalogue($directory, $language);
        $missing = array_values(array_filter(array_unique($used), static fn (string $message): bool => '' !== $message && !isset($catalogue[$message])));
        self::assertSame([], $missing);
    }

    #[DataProvider('languages')]
    public function testEveryValidationMessageOfTheCodeIsInTheCatalogue(string $language): void
    {
        $catalogue = self::catalogue('I18n', $language);
        $messages = [];
        foreach (['Validation', 'Transfer'] as $directory) {
            foreach (glob(self::ROOT.'/'.$directory.'/*.php') ?: [] as $file) {
                preg_match_all('/(?:error\([^;]*?|trans\()\'([A-Z](?:[^\'\\\\]|\\\\.)* (?:[^\'\\\\]|\\\\.)*)\'/', (string) file_get_contents($file), $matches);
                foreach ($matches[1] as $message) {
                    $messages[str_replace("\\'", "'", $message)] = true;
                }
            }
        }
        $messages = array_keys($messages);

        $missing = array_values(array_filter($messages, static fn (string $message): bool => !isset($catalogue[$message])));
        self::assertSame([], $missing);
        self::assertNotSame([], $messages);
    }

    /** @return array<string, string> */
    private static function catalogue(string $directory, string $language): array
    {
        return require self::ROOT.'/'.$directory.'/'.$language.'.php';
    }

    /** @return list<string> */
    private static function placeholders(string $text): array
    {
        preg_match_all('/%[A-Za-z_]+%|%d|%s/', $text, $matches);
        sort($matches[0]);

        return $matches[0];
    }
}
