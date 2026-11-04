<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Support;

use CookieConsentManager\Controller\ConsentController;
use CookieConsentManager\Controller\PanelController;
use CookieConsentManager\Controller\PreferencesPageController;
use CookieConsentManager\Controller\ServedScriptController;
use CookieConsentManager\Controller\StyleSheetController;
use CookieConsentManager\Front\FrontUrls;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/** The real routes of the module controllers (read from their attributes) behind a router whose request context carries a base path. */
final class ModuleRoutes
{
    public static function urls(string $baseUrl = ''): FrontUrls
    {
        $routes = new RouteCollection();
        foreach ([ConsentController::class, PanelController::class, PreferencesPageController::class, ServedScriptController::class, StyleSheetController::class] as $controller) {
            foreach ((new \ReflectionClass($controller))->getMethods() as $method) {
                foreach ($method->getAttributes(RouteAttribute::class) as $attribute) {
                    $route = $attribute->newInstance();
                    $routes->add((string) $route->getName(), new Route((string) $route->getPath(), requirements: $route->getRequirements()));
                }
            }
        }

        return new FrontUrls(new UrlGenerator($routes, new RequestContext($baseUrl)));
    }
}
