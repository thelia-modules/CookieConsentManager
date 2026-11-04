<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\LocalizedNumber;
use PHPUnit\Framework\TestCase;

final class LocalizedNumberTest extends TestCase
{
    public function testFrenchUsesTheDecimalCommaAndEnglishThePoint(): void
    {
        self::assertSame('47,1', LocalizedNumber::format(47.1, 'fr_FR'));
        self::assertSame('47.1', LocalizedNumber::format(47.1, 'en_US'));
        self::assertSame('1,23', LocalizedNumber::format(1.234, 'fr', 2));
        self::assertSame("1\u{202F}234", LocalizedNumber::format(1234, 'fr_FR', 0));
        self::assertSame('-', LocalizedNumber::format(null, 'fr_FR'));
    }
}
