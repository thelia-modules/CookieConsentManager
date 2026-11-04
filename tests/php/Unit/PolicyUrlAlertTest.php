<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\Alert;
use CookieConsentManager\Admin\PolicyUrlAlert;
use CookieConsentManager\Admin\PolicyUrlProbe;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PolicyUrlAlertTest extends TestCase
{
    public function testAPageThatAnswers404RaisesADangerAlertAndAnOkPageDoesNot(): void
    {
        self::assertSame('policy_unreachable', $this->alert(404)->code);
        self::assertSame(Alert::DANGER, $this->alert(404)->severity);
        self::assertStringContainsString('404', (string) $this->alert(404)->message);
        self::assertNull($this->alert(200));
    }

    public function testAnUnreachableShopIsReportedAsUnverifiedNotAsBroken(): void
    {
        $client = new MockHttpClient(static fn () => throw new \RuntimeException('down'));

        self::assertSame('policy_unverified', $this->alertWith($client)?->code);
    }

    public function testNoUrlNoAlert(): void
    {
        self::assertNull($this->alertWith(new MockHttpClient(), null));
    }

    public function testTheAnswerIsCachedAndTheRequestGoesToTheHostOfTheShop(): void
    {
        $urls = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;

            return new MockResponse('', ['http_code' => 404]);
        });
        $stack = new RequestStack();
        $stack->push(Request::create('https://shop.test/admin'));
        $probe = new PolicyUrlProbe($client, $stack, new ArrayAdapter());

        $probe->status('/politique');
        $probe->status('/politique');

        self::assertSame(['https://shop.test/politique'], $urls);
    }

    private function alert(int $status): ?Alert
    {
        return $this->alertWith(new MockHttpClient(new MockResponse('', ['http_code' => $status])));
    }

    private function alertWith(MockHttpClient $client, ?string $url = '/politique'): ?Alert
    {
        $stack = new RequestStack();
        $stack->push(Request::create('https://shop.test/admin'));
        $translator = new class implements TranslatorInterface {
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return strtr($id, $parameters);
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };

        return (new PolicyUrlAlert(new PolicyUrlProbe($client, $stack, new ArrayAdapter()), $translator))->check($url);
    }
}
