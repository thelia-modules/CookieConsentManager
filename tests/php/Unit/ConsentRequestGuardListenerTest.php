<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\EventListener\ConsentRequestGuardListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Core\EventListener\RequestListener;

final class ConsentRequestGuardListenerTest extends TestCase
{
    public function testAnswersTheContractBeforeTheCoreDecodesTheBody(): void
    {
        $cases = [
            'malformed' => ['{x', 400, 'invalid_json'],
            'too large' => ['{"a":"'.str_repeat('a', 9000).'"}', 413, 'too_large'],
        ];

        foreach ($cases as $name => [$body, $status, $error]) {
            $event = $this->event('POST', '/ccm/consent', $body);
            (new ConsentRequestGuardListener())->onRequest($event);

            self::assertSame($status, $event->getResponse()?->getStatusCode(), $name);
            self::assertSame(['error' => $error], json_decode((string) $event->getResponse()->getContent(), true), $name);
        }
    }

    public function testLeavesAValidBodyAndOtherRoutesAlone(): void
    {
        foreach ([['POST', '/ccm/consent', '{"format":1}'], ['POST', '/ccm/consent', ''], ['GET', '/ccm/consent', '{x'], ['POST', '/fr/panier', '{x']] as [$method, $path, $body]) {
            $event = $this->event($method, $path, $body);
            (new ConsentRequestGuardListener())->onRequest($event);

            self::assertNull($event->getResponse(), $method.' '.$path.' '.$body);
        }
    }

    public function testRunsBeforeTheJsonBodyListenerOfTheCore(): void
    {
        self::assertGreaterThan(
            (new \ReflectionMethod(RequestListener::class, 'jsonBody'))->getAttributes(AsEventListener::class)[0]->newInstance()->priority,
            (new \ReflectionMethod(ConsentRequestGuardListener::class, 'onRequest'))->getAttributes(AsEventListener::class)[0]->newInstance()->priority,
        );
    }

    private function event(string $method, string $path, string $body): RequestEvent
    {
        $request = Request::create($path, $method, [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);

        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
