<?php

declare(strict_types=1);

namespace CookieConsentManager\Controller;

use CookieConsentManager\Admin\ActiveLanguages;
use CookieConsentManager\Front\FrontConfigProvider;
use CookieConsentManager\Front\FrontThemeActivator;
use CookieConsentManager\Front\FrontUrls;
use CookieConsentManager\Front\TemplateContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * The second level (preferences panel) of a published version, in one language. The pages do not carry it: the engine
 * fetches it when the visitor opens it, and the browser keeps it for good because the URL changes with the version, the
 * module and the templates of the theme (it holds the hash of the served configuration).
 */
#[AsController]
final readonly class PanelController
{
    public const ROUTE = 'cookieconsentmanager_panel';

    public function __construct(
        private FrontConfigProvider $configs,
        private TemplateContext $context,
        private FrontThemeActivator $theme,
        private Environment $twig,
        private FrontUrls $urls,
        private ActiveLanguages $languages,
    ) {
    }

    #[Route('/ccm/panel/{version}-{hash}-{locale}.html', name: self::ROUTE, requirements: ['version' => '\d{1,9}', 'hash' => '[0-9a-f]{12}', 'locale' => '[a-z]{2}(?:_[A-Z]{2})?'], methods: ['GET'], stateless: true)]
    public function __invoke(int $version, string $hash, string $locale): Response
    {
        // Only the languages of the shop: any other value would fill the cache with configurations nobody asked for.
        $front = \in_array($locale, $this->languages->locales(), true) ? $this->configs->forVersion($version, $locale) : null;
        if (null === $front) {
            throw new NotFoundHttpException();
        }

        // A page cached before an update of the module or of a theme still points to the previous hash: it gets the current panel of the same version.
        if (!hash_equals($front->config['hash'], $hash)) {
            $redirect = new RedirectResponse($this->urls->panel($version, $front->config['hash'], $locale), Response::HTTP_FOUND);
            $redirect->headers->set('Cache-Control', 'public, max-age=60');

            return $redirect;
        }

        $this->theme->activate();

        return new Response($this->twig->render('CookieConsentManager/preferences-panel.html.twig', $this->context->build($front)), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
