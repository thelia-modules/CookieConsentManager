<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Publication\CanonicalJson;
use CookieConsentManager\Publication\ConfigDiff;
use CookieConsentManager\Registry\BrowserFamily;
use CookieConsentManager\Registry\ConsentCookie;
use CookieConsentManager\Registry\IpTruncator;
use CookieConsentManager\Registry\Uuid4;
use PHPUnit\Framework\TestCase;

final class SmallPartsTest extends TestCase
{
    public function testUuidV4(): void
    {
        $generated = Uuid4::generate();

        self::assertTrue(Uuid4::isValid($generated));
        self::assertNotSame($generated, Uuid4::generate());
        self::assertTrue(Uuid4::isValid('4F1C2B9E-8D7A-4C3E-9B1F-2A6D5E7C8B90'));
        foreach (['', 'nope', '4f1c2b9e-8d7a-1c3e-9b1f-2a6d5e7c8b90', '4f1c2b9e-8d7a-4c3e-cb1f-2a6d5e7c8b90', null, 42] as $invalid) {
            self::assertFalse(Uuid4::isValid($invalid));
        }
    }

    public function testCookieCodecIsBase64UrlWithoutPaddingAndRejectsAnUnknownFormat(): void
    {
        $encoded = ConsentCookie::encode(['f' => 1, 'id' => '4f1c2b9e-8d7a-4c3e-9b1f-2a6d5e7c8b90', 'p' => ['analytics' => 1]]);

        self::assertDoesNotMatchRegularExpression('/[+\/=]/', $encoded);
        self::assertSame(1, ConsentCookie::decode($encoded)['f']);
        self::assertNull(ConsentCookie::decode(ConsentCookie::encode(['f' => 2, 'id' => '4f1c2b9e-8d7a-4c3e-9b1f-2a6d5e7c8b90'])));
        self::assertNull(ConsentCookie::decode('%%%'));
        self::assertNull(ConsentCookie::decode(null));
        self::assertNull(ConsentCookie::decode(str_repeat('a', 3501)));
    }

    public function testIpTruncation(): void
    {
        self::assertSame('203.0.113.0', IpTruncator::truncate('203.0.113.77'));
        self::assertSame('203.0.113.0', IpTruncator::truncate('::ffff:203.0.113.77'), 'a dual-stack server reports an IPv4 visitor as an IPv4-mapped address');
        self::assertSame('2001:db8:abcd::', IpTruncator::truncate('2001:db8:abcd:1234:5678::1'));
        self::assertNull(IpTruncator::truncate('not an ip'));
        self::assertNull(IpTruncator::truncate(null));
    }

    public function testBrowserFamilyKeepsTheMajorVersionOnly(): void
    {
        self::assertSame('Chrome 130', BrowserFamily::parse('Mozilla/5.0 (X11; Linux) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.6723.58 Safari/537.36')['label']);
        self::assertSame('Firefox 131', BrowserFamily::parse('Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0')['label']);
        self::assertSame('Safari 17', BrowserFamily::parse('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15')['label']);
        self::assertSame('Edge 130', BrowserFamily::parse('Mozilla/5.0 AppleWebKit/537.36 Chrome/130.0 Safari/537.36 Edg/130.0.2849.46')['label']);
        self::assertTrue(BrowserFamily::parse('Mozilla/5.0 (compatible; Googlebot/2.1)')['bot']);
        self::assertTrue(BrowserFamily::parse(null)['bot']);
        self::assertFalse(BrowserFamily::parse('Mozilla/5.0 (X11) Chrome/130.0 Safari/537.36')['bot']);
    }

    public function testCanonicalJsonDoesNotDependOnKeyOrder(): void
    {
        self::assertSame(CanonicalJson::hash(['b' => 1, 'a' => ['y' => 1, 'x' => 2]]), CanonicalJson::hash(['a' => ['x' => 2, 'y' => 1], 'b' => 1]));
        self::assertNotSame(CanonicalJson::hash(['a' => [1, 2]]), CanonicalJson::hash(['a' => [2, 1]]), 'a list keeps its order');
    }

    public function testConfigDiffIgnoresServiceOrderAndMetaAndSeesAnyChange(): void
    {
        $a = ['general' => ['layout' => 'bar'], 'services' => [['key' => 'a', 'enabled' => true], ['key' => 'b', 'enabled' => false]], '_meta' => ['x' => 1]];
        $b = ['general' => ['layout' => 'bar'], 'services' => [['key' => 'b', 'enabled' => false], ['key' => 'a', 'enabled' => true]]];

        self::assertSame([], ConfigDiff::between($a, $b));

        $b['services'][0]['enabled'] = true;
        $b['general']['extra'] = null;

        self::assertSame(['general.extra', 'services.b.enabled'], ConfigDiff::between($a, $b));
    }
}
