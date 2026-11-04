<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Registry\ConsentRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ConsentRateLimiterTest extends TestCase
{
    public function testAddressesOfTheSameIpv6SlashSixtyFourShareOneCounter(): void
    {
        $limiter = new ConsentRateLimiter(new ArrayAdapter(), 2);

        self::assertNull($limiter->consume('2001:db8:1:2::1'));
        self::assertNull($limiter->consume('2001:db8:1:2:aaaa:bbbb:cccc:dddd'));
        self::assertNotNull($limiter->consume('2001:db8:1:2:ffff::9'), 'rotating inside the /64 does not reset the counter');
    }

    public function testAnotherIpv6SlashSixtyFourHasItsOwnCounter(): void
    {
        $limiter = new ConsentRateLimiter(new ArrayAdapter(), 1);

        self::assertNull($limiter->consume('2001:db8:1:2::1'));
        self::assertNotNull($limiter->consume('2001:db8:1:2::2'));
        self::assertNull($limiter->consume('2001:db8:1:3::1'), 'the next /64 is another visitor');
    }

    public function testIpv4StaysOneCounterPerAddressAndAMappedAddressIsAnIpv4(): void
    {
        $limiter = new ConsentRateLimiter(new ArrayAdapter(), 1);

        self::assertNull($limiter->consume('203.0.113.7'));
        self::assertNotNull($limiter->consume('203.0.113.7'));
        self::assertNull($limiter->consume('203.0.113.8'), 'a neighbour address is not grouped');
        self::assertNull($limiter->consume('::ffff:203.0.113.9'));
        self::assertNull($limiter->consume('::ffff:203.0.113.10'), 'mapped addresses are not all one /64');
        self::assertNotNull($limiter->consume('::ffff:203.0.113.10'));
    }
}
