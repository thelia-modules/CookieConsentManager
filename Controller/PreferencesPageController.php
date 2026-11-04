<?php

declare(strict_types=1);

namespace CookieConsentManager\Controller;

use CookieConsentManager\Front\ConsentThemeHook;
use CookieConsentManager\Front\FrontConfigProvider;
use CookieConsentManager\Front\FrontThemeActivator;
use CookieConsentManager\Front\TemplateContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/** Target of the footer link without JavaScript (SPEC 4.3): explains how to manage the choices and lists the services. */
#[AsController]
final readonly class PreferencesPageController
{
    public const PATH = '/ccm/preferences';
    public const ROUTE = 'cookieconsentmanager_preferences';

    public function __construct(
        private FrontConfigProvider $configs,
        private TemplateContext $context,
        private ConsentThemeHook $hook,
        private Environment $twig,
        private FrontThemeActivator $theme,
    ) {
    }

    #[Route(self::PATH, name: self::ROUTE, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $front = $this->configs->forRequest($request);
        if (null === $front) {
            throw new NotFoundHttpException();
        }

        $this->theme->activate();

        $response = new Response($this->twig->render('CookieConsentManager/preferences-page.html.twig', $this->context->build($front) + [
            'head' => $this->hook->render('layout.head.top', []),
            'body' => $this->hook->render('layout.body.top', []),
        ]));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
