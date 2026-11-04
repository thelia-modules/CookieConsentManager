<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\PolicyUrlProbe;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\RequestStack;

final class PolicyUrlProbeTest extends TestCase
{
    /** @var list<string> */
    private array $reached = [];

    private function probe(callable $answer): PolicyUrlProbe
    {
        $client = new MockHttpClient(function (string $method, string $url) use ($answer): MockResponse {
            $this->reached[] = $url;

            return $answer($url);
        });

        return new PolicyUrlProbe($client, new RequestStack(), new ArrayAdapter());
    }

    /** @return iterable<string, array{string}> */
    public static function privateAddresses(): iterable
    {
        yield 'loopback' => ['http://127.0.0.1/'];
        yield 'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'];
        yield 'private network' => ['https://10.0.0.5/admin'];
        yield 'ipv6 loopback' => ['http://[::1]/'];
    }

    #[DataProvider('privateAddresses')]
    public function testAPrivateAddressIsNeverRequested(string $url): void
    {
        $probe = $this->probe(static fn (): MockResponse => new MockResponse('secret', ['http_code' => 200]));

        self::assertNull($probe->status($url), 'reported as not checked');
        self::assertSame([], $this->reached, 'no request left');
    }

    public function testARedirectToAPrivateAddressIsNotFollowed(): void
    {
        $probe = $this->probe(static fn (string $url): MockResponse => str_contains($url, '93.184.216.34')
            ? new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location: http://169.254.169.254/latest/meta-data/'], 'redirect_url' => 'http://169.254.169.254/latest/meta-data/'])
            : new MockResponse('secret', ['http_code' => 200]));

        self::assertNull($probe->status('http://93.184.216.34/page'));
        self::assertSame(['http://93.184.216.34/page'], $this->reached, 'the redirect target never left');
    }

    public function testAPublicAddressIsStillChecked(): void
    {
        $probe = $this->probe(static fn (): MockResponse => new MockResponse('', ['http_code' => 404]));

        self::assertSame(404, $probe->status('https://93.184.216.34/gone'));
    }
}
