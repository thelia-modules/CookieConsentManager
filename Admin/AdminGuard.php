<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\CookieConsentManager;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Security\Exception\TokenAuthenticationException;
use Thelia\Core\Security\SecurityContext;
use Thelia\Tools\TokenProvider;

/** The two questions every write of the back-office asks: is the session token there, and is the right granted. */
final readonly class AdminGuard
{
    public function __construct(
        private TokenProvider $tokens,
        private SecurityContext $security,
    ) {
    }

    public function tokenIsValid(Request $request): bool
    {
        try {
            return $this->tokens->checkToken((string) $request->request->get('_token', ''));
        } catch (TokenAuthenticationException) {
            return false;
        }
    }

    public function isGranted(string $access): bool
    {
        return $this->security->isGranted(['ADMIN'], [], [CookieConsentManager::getModuleCode()], [$access]);
    }
}
