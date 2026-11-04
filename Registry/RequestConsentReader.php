<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

use Symfony\Component\HttpFoundation\Request;

/** Reads the consent carried by a request (PHP side of the Twig helpers). */
final readonly class RequestConsentReader
{
    public function hasConsent(Request $request, string $service, int $repromptDays, int $substantialVersion): bool
    {
        return 1 === ($this->fresh($request, $repromptDays, $substantialVersion)['s'][$service] ?? null);
    }

    public function hasPurposeConsent(Request $request, string $purpose, int $repromptDays, int $substantialVersion): bool
    {
        return 1 === ($this->fresh($request, $repromptDays, $substantialVersion)['p'][$purpose] ?? null);
    }

    /** @return array<string, mixed>|null the decoded cookie, null without a valid choice */
    public function state(Request $request): ?array
    {
        return ConsentCookie::decode($request->cookies->get(ConsentCookie::NAME));
    }

    /** @return array<string, mixed>|null the choice, null when absent, expired or older than the last substantial version */
    private function fresh(Request $request, int $repromptDays, int $substantialVersion): ?array
    {
        $state = $this->state($request);

        return null === $state || ConsentCookie::isStale($state, $repromptDays, $substantialVersion, time()) ? null : $state;
    }
}
