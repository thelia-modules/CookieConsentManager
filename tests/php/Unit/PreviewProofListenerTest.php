<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\EventListener\PreviewProofListener;
use CookieConsentManager\Front\PreviewToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class PreviewProofListenerTest extends TestCase
{
    public function testOnlyAPagePreviewedByAnAdministratorReceivesTheProof(): void
    {
        $tokens = new PreviewToken('secret');
        $listener = new PreviewProofListener($tokens);
        $kernel = $this->createStub(HttpKernelInterface::class);
        $token = str_repeat('a', 32);

        $plain = new Response();
        $listener->onResponse(new ResponseEvent($kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST, $plain));
        self::assertCount(0, $plain->headers->getCookies());

        $request = Request::create('/');
        $request->attributes->set(PreviewToken::PROOF_ATTRIBUTE, $token);
        $previewed = new Response();
        $listener->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $previewed));
        $cookie = $previewed->headers->getCookies()[0];
        self::assertSame(PreviewToken::PROOF_COOKIE, $cookie->getName());
        self::assertTrue($tokens->proofIsValid($token, $cookie->getValue()));
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('/ccm/', $cookie->getPath());
        self::assertTrue($previewed->headers->hasCacheControlDirective('no-store'));
    }

    public function testTheProofCookieIsScopedUnderTheBasePathOfAShopInASubDirectory(): void
    {
        $request = Request::create('/shop/fr/', 'GET', [], [], [], ['SCRIPT_FILENAME' => '/var/www/shop/index.php', 'SCRIPT_NAME' => '/shop/index.php', 'PHP_SELF' => '/shop/index.php']);
        $request->attributes->set(PreviewToken::PROOF_ATTRIBUTE, str_repeat('a', 32));
        $response = new Response();

        (new PreviewProofListener(new PreviewToken('secret')))->onResponse(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertSame('/shop/ccm/', $response->headers->getCookies()[0]->getPath());
    }
}
