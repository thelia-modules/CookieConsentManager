<?php

declare(strict_types=1);

namespace CookieConsentManager\EventListener;

use CookieConsentManager\Front\PreviewToken;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** A page rendered in preview (administrator connected, valid token) hands the browser the proof the script route asks for. */
final readonly class PreviewProofListener
{
    public function __construct(private PreviewToken $tokens)
    {
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $token = $request->attributes->get(PreviewToken::PROOF_ATTRIBUTE);

        if (!$event->isMainRequest() || !\is_string($token)) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->setCookie(Cookie::create(PreviewToken::PROOF_COOKIE, $this->tokens->proofFor($token), 0, $request->getBasePath().'/ccm/', null, $request->isSecure(), true, false, Cookie::SAMESITE_LAX));
        $response->headers->set('Cache-Control', 'no-store, private');
    }
}
