<?php

declare(strict_types=1);

namespace CookieConsentManager\Controller;

use CookieConsentManager\Registry\ConsentPayloadValidator;
use CookieConsentManager\Registry\ConsentRateLimiter;
use CookieConsentManager\Registry\ConsentRecorder;
use CookieConsentManager\Registry\ConsentRejectedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ConsentController
{
    public const PATH = '/ccm/consent';
    public const ROUTE = 'cookieconsentmanager_consent';
    public const MAX_BODY_BYTES = 8192;

    public function __construct(
        private ConsentRateLimiter $limiter,
        private ConsentPayloadValidator $validator,
        private ConsentRecorder $recorder,
    ) {
    }

    // stateless: true, otherwise every POST would open a session.
    #[Route(self::PATH, name: self::ROUTE, methods: ['POST'], stateless: true)]
    public function __invoke(Request $request): Response
    {
        try {
            $this->guardOrigin($request);

            $retryAfter = $this->limiter->consume($request->getClientIp() ?? 'unknown');
            if (null !== $retryAfter) {
                throw new ConsentRejectedException(429, 'rate_limited', retryAfter: $retryAfter);
            }

            $data = $this->decode($request);
            $payload = $this->validator->validate($data);
            $recorded = $this->recorder->record($payload, $request);
        } catch (ConsentRejectedException $rejection) {
            return $this->rejection($rejection);
        }

        $response = new JsonResponse([
            'consent_id' => $recorded->consentId,
            'recorded_at' => $recorded->recordedAt->format('Y-m-d\TH:i:s\Z'),
        ]);
        $response->headers->setCookie($recorded->cookie);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function guardOrigin(Request $request): void
    {
        $site = $request->headers->get('Sec-Fetch-Site');
        if (null !== $site && !\in_array($site, ['same-origin', 'none'], true)) {
            throw new ConsentRejectedException(403, 'forbidden_origin');
        }

        $origin = $request->headers->get('Origin');
        if (null === $origin) {
            // No Origin: only a browser that vouches for a same-origin request is accepted.
            if (null === $site) {
                throw new ConsentRejectedException(403, 'forbidden_origin');
            }

            return;
        }

        $host = parse_url($origin, \PHP_URL_HOST);
        $port = parse_url($origin, \PHP_URL_PORT);
        $originHost = \is_string($host) ? strtolower($host).(\is_int($port) ? ':'.$port : '') : '';

        if ($originHost !== strtolower($request->getHttpHost())) {
            throw new ConsentRejectedException(403, 'forbidden_origin');
        }
    }

    /** @return array<string, mixed> */
    private function decode(Request $request): array
    {
        $length = $request->headers->get('Content-Length');
        $body = null !== $length && (int) $length > self::MAX_BODY_BYTES ? null : $request->getContent();
        if (null === $body || \strlen($body) > self::MAX_BODY_BYTES) {
            throw new ConsentRejectedException(413, 'too_large');
        }

        if ('json' !== $request->getContentTypeFormat()) {
            throw new ConsentRejectedException(400, 'invalid_json');
        }

        try {
            $data = json_decode($body, true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ConsentRejectedException(400, 'invalid_json');
        }

        if (!\is_array($data) || array_is_list($data)) {
            throw new ConsentRejectedException(400, 'invalid_json');
        }

        return $data;
    }

    private function rejection(ConsentRejectedException $rejection): JsonResponse
    {
        $body = ['error' => $rejection->error];
        if (null !== $rejection->field) {
            $body['field'] = $rejection->field;
        }

        $response = new JsonResponse($body, $rejection->status);
        $response->headers->set('Cache-Control', 'no-store');
        if (null !== $rejection->retryAfter) {
            $response->headers->set('Retry-After', (string) $rejection->retryAfter);
        }

        return $response;
    }
}
