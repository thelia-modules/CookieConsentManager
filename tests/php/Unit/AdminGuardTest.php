<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\AdminGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Security\Exception\TokenAuthenticationException;
use Thelia\Core\Security\SecurityContext;
use Thelia\Tools\TokenProvider;

final class AdminGuardTest extends TestCase
{
    public function testTheTokenIsReadFromTheBodyAndAMismatchIsRefused(): void
    {
        $tokens = $this->createMock(TokenProvider::class);
        $tokens->method('checkToken')->willReturnCallback(static function (string $value): bool {
            if ('good' !== $value) {
                throw new TokenAuthenticationException('bad');
            }

            return true;
        });
        $guard = new AdminGuard($tokens, $this->createMock(SecurityContext::class));

        self::assertTrue($guard->tokenIsValid(new Request([], ['_token' => 'good'])));
        self::assertFalse($guard->tokenIsValid(new Request([], ['_token' => 'forged'])));
        self::assertFalse($guard->tokenIsValid(new Request([], [])), 'no token at all');
        self::assertFalse($guard->tokenIsValid(new Request(['_token' => 'good'])), 'a token in the query string is not read');
    }

    public function testAnAnonymousVisitorHoldsNoRightAndEachAccessIsAskedOnTheModule(): void
    {
        $security = $this->createMock(SecurityContext::class);
        $security->method('checkRole')->willReturn(null);
        $guard = new AdminGuard($this->createMock(TokenProvider::class), $security);

        foreach (['VIEW', 'UPDATE', 'CREATE', 'DELETE'] as $access) {
            self::assertFalse($guard->isGranted($access), $access);
        }
    }
}
