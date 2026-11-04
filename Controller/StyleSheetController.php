<?php

declare(strict_types=1);

namespace CookieConsentManager\Controller;

use CookieConsentManager\Front\FrontUrls;
use CookieConsentManager\Front\StyleSheet;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class StyleSheetController
{
    public const ROUTE = 'cookieconsentmanager_style';

    public function __construct(
        private StyleSheet $style,
        private FrontUrls $urls,
    ) {
    }

    // Named after its own hash: the content of a name never changes, so it never expires.
    #[Route('/ccm/{hash}.css', name: self::ROUTE, requirements: ['hash' => '[0-9a-f]{12}'], methods: ['GET'], stateless: true)]
    public function __invoke(string $hash): Response
    {
        // A page cached before an update of the module still points to the previous name: it gets the current stylesheet.
        if (!hash_equals($this->style->hash(), $hash)) {
            $redirect = new RedirectResponse($this->urls->style($this->style->hash()), Response::HTTP_FOUND);
            $redirect->headers->set('Cache-Control', 'public, max-age=60');

            return $redirect;
        }

        return new Response($this->style->minified(), 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
