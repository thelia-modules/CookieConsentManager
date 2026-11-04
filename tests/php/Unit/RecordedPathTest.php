<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Registry\RecordedPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecordedPathTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function paths(): iterable
    {
        yield 'password reset' => ['/password/reset/12.1893456000.0f1e2d3c4b5a69788796a5b4c3d2e1f0', '/password/reset/*'];
        yield 'password reset with a language' => ['/fr/password/reset/anything', '/fr/password/reset/*'];
        yield 'token segment elsewhere' => ['/account/12.1893456000.0f1e2d3c4b5a69788796a5b4c3d2e1f0/edit', '/account/*/edit'];
        yield 'order tracking' => ['/en/order/track/abc123', '/en/order/track/*'];
        yield 'ordinary page' => ['/fr/creme-solaire-spf50.html', '/fr/creme-solaire-spf50.html'];
        yield 'short hexadecimal word' => ['/fr/deadbeef-cafe', '/fr/deadbeef-cafe'];
    }

    #[DataProvider('paths')]
    public function testMasksWhatCouldBeASecret(string $sent, string $stored): void
    {
        self::assertSame($stored, RecordedPath::mask($sent));
    }
}
