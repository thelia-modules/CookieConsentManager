<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\Alert;
use CookieConsentManager\Admin\AlertProvider\AlertText;
use CookieConsentManager\Admin\AlertProvider\PreviewAlerts;
use CookieConsentManager\EventListener\PreviewProofListener;
use CookieConsentManager\Front\PreviewToken;
use CookieConsentManager\Publication\CanonicalJson;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** An empty or short secret makes the HMAC computable by anyone: no token is valid and none is issued. */
final class PreviewTokenSecretTest extends TestCase
{
    private const DRAFT = ['a' => 1];

    /** @return iterable<string, array{string}> */
    public static function weakSecrets(): iterable
    {
        yield 'empty' => [''];
        yield 'one byte' => ['x'];
        yield '31 bytes' => [str_repeat('k', 31)];
    }

    #[DataProvider('weakSecrets')]
    public function testAWeakSecretIssuesNothingAndValidatesNothing(string $secret): void
    {
        $tokens = new PreviewToken($secret);

        self::assertFalse($tokens->isAvailable());
        self::assertNull($tokens->forDraft(self::DRAFT));
        self::assertNull($tokens->proofFor(str_repeat('a', 32)));

        $forged = substr(hash_hmac('sha256', CanonicalJson::encode(self::DRAFT), $secret), 0, 32);
        self::assertFalse($tokens->isValid($forged, self::DRAFT), 'the token computed from the same weak secret is refused');
        $proof = substr(hash_hmac('sha256', 'ccm-preview-proof|'.$forged, $secret), 0, 32);
        self::assertFalse($tokens->proofIsValid($forged, $proof));
    }

    public function testASecretOfThirtyTwoBytesIsEnough(): void
    {
        $tokens = new PreviewToken(str_repeat('k', 32));
        $token = $tokens->forDraft(self::DRAFT);

        self::assertTrue($tokens->isAvailable());
        self::assertNotNull($token);
        self::assertTrue($tokens->isValid($token, self::DRAFT));
        self::assertTrue($tokens->proofIsValid($token, $tokens->proofFor($token)));
    }

    public function testNoProofCookieIsSetWithAWeakSecret(): void
    {
        $request = Request::create('/');
        $request->attributes->set(PreviewToken::PROOF_ATTRIBUTE, str_repeat('a', 32));
        $response = new Response();

        (new PreviewProofListener(new PreviewToken('')))->onResponse(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertCount(0, $response->headers->getCookies());
    }

    public function testTheDashboardExplainsWhyThePreviewIsUnavailable(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters): string => strtr($id, $parameters));

        $weak = (new PreviewAlerts(new PreviewToken(''), new AlertText($translator)))->alerts([]);
        self::assertCount(1, $weak);
        self::assertSame('preview_secret', $weak[0]->code);
        self::assertSame(Alert::WARNING, $weak[0]->severity);
        self::assertStringContainsString('APP_SECRET', $weak[0]->message);
        self::assertStringContainsString('32', $weak[0]->message);

        self::assertSame([], (new PreviewAlerts(new PreviewToken(str_repeat('k', 32)), new AlertText($translator)))->alerts([]));
    }
}
