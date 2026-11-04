<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

use Symfony\Component\HttpFoundation\Request;

/** Reads the consent carried by a request (PHP API of SPEC 10.3, behind the Twig helpers). */
final readonly class RequestConsentReader
{
    public function hasConsent(Request $request, string $service): bool
    {
        return 1 === ($this->state($request)['s'][$service] ?? null);
    }

    public function hasPurposeConsent(Request $request, string $purpose): bool
    {
        return 1 === ($this->state($request)['p'][$purpose] ?? null);
    }

    /** @return array<string, mixed>|null the decoded cookie, null without a valid choice */
    public function state(Request $request): ?array
    {
        return ConsentCookie::decode($request->cookies->get(ConsentCookie::NAME));
    }
}
