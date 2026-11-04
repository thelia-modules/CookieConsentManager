<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Validation\TermsPageUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TermsPageUrlTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function addresses(): iterable
    {
        yield 'thelia terms page' => ['/terms-and-conditions.html', true];
        yield 'french terms of sale' => ['https://shop.example/conditions-generales-de-vente', true];
        yield 'cgv' => ['/cgv', true];
        yield 'legal notice' => ['/mentions-legales', true];
        yield 'query ignored' => ['/politique?ref=cgv', false];
        yield 'cookie policy' => ['/politique-de-cookies', false];
        yield 'cookies and terms of use' => ['/conditions-utilisation-des-cookies', false];
        yield 'privacy' => ['https://shop.example/politique-de-confidentialite', false];
    }

    #[DataProvider('addresses')]
    public function testItRecognisesThePageOfTermsAndConditions(string $url, bool $expected): void
    {
        self::assertSame($expected, TermsPageUrl::matches($url));
    }
}
