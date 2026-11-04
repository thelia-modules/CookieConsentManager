<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

use Symfony\Component\HttpFoundation\Request;

/**
 * The endpoint is stateless, so no session is started for it: a customer is known only
 * when the request already carries a session Thelia could read.
 */
class CustomerIdResolver
{
    public function resolve(Request $request): ?int
    {
        if (!$request->hasSession(true) || !$request->hasPreviousSession()) {
            return null;
        }

        $user = $request->getSession()->get('thelia.customer_user');

        return \is_object($user) && method_exists($user, 'getId') ? (int) $user->getId() : null;
    }
}
