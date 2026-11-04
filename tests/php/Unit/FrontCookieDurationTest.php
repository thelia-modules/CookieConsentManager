<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Front\CookieDuration;
use CookieConsentManager\Front\FrontTexts;
use CookieConsentManager\Tests\Support\CatalogueTranslator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrontCookieDurationTest extends TestCase
{
    /** @return iterable<string, array{0: ?int, 1: string, 2: string}> */
    public static function durations(): iterable
    {
        yield 'unknown' => [null, 'fr', ''];
        yield 'session' => [0, 'fr', 'session'];
        yield 'one day' => [1, 'fr', '1 jour'];
        yield 'days' => [30, 'en', '30 days'];
        yield 'two months' => [60, 'fr', '2 mois'];
        yield 'thirteen months' => [390, 'fr', '13 mois'];
        yield 'two years' => [730, 'en', '2 years'];
        yield 'two years rounded' => [800, 'fr', '2 ans'];
    }

    public function testALifetimeUnderADayIsWrittenInMinutes(): void
    {
        $texts = (new FrontTexts(new CatalogueTranslator()))->forLanguage('fr');

        self::assertSame('30 minutes', CookieDuration::format(null, $texts, 30));
        self::assertSame('1 minute', CookieDuration::format(null, $texts, 1));
        self::assertSame('1 jour', CookieDuration::format(null, $texts, 1440));
        self::assertSame('6 mois', CookieDuration::months(6, $texts));
    }

    #[DataProvider('durations')]
    public function testADurationIsWrittenInTheLanguageOfTheVisitor(?int $days, string $language, string $expected): void
    {
        self::assertSame($expected, CookieDuration::format($days, (new FrontTexts(new CatalogueTranslator()))->forLanguage($language)));
    }
}
