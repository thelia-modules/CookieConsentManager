<?php

declare(strict_types=1);

namespace CookieConsentManager\EventListener;

use CookieConsentManager\Controller\ConsentController;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Thelia decodes every JSON body at priority 128 (RequestListener::jsonBody) and lets a
 * JsonException escape as a 500 on a malformed one, before the router and the controller.
 * The endpoint owes its callers a 400 (and a 413 before any decoding): this listener answers
 * first, for the registry route only.
 */
final class ConsentRequestGuardListener
{
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 200)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !$request->isMethod('POST') || ConsentController::PATH !== $request->getPathInfo()) {
            return;
        }

        $length = $request->headers->get('Content-Length');
        $tooLarge = null !== $length && (int) $length > ConsentController::MAX_BODY_BYTES;
        $body = $tooLarge ? '' : $request->getContent();

        if ($tooLarge || \strlen($body) > ConsentController::MAX_BODY_BYTES) {
            $event->setResponse($this->error('too_large', 413));

            return;
        }

        if ('' !== $body && !json_validate($body)) {
            $event->setResponse($this->error('invalid_json', 400));
        }
    }

    private function error(string $code, int $status): JsonResponse
    {
        $response = new JsonResponse(['error' => $code], $status);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
