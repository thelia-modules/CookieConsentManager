<?php

declare(strict_types=1);

namespace CookieConsentManager\EventListener;

use CookieConsentManager\Controller\PreferencesPageController;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * `stateless: true` on a route is not enough in Thelia: the listeners of the core that start
 * the session (priority PHP_INT_MAX - 2, then 128) run before the router has set `_stateless`
 * on the request, so every POST would open a session and the response would fail with
 * UnexpectedSessionUsageException. The core only skips the session for `/api/` or when the
 * attribute is already set: this listener sets it, for the public routes of the module, before.
 */
final class StatelessRoutesListener
{
    public const PREFIX = '/ccm/';
    // A page of the site: it renders the theme machinery, which uses the session (language of the visitor).
    public const PAGES = [PreferencesPageController::PATH];

    #[AsEventListener(event: KernelEvents::REQUEST, priority: \PHP_INT_MAX - 1)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($event->isMainRequest() && str_starts_with($request->getPathInfo(), self::PREFIX) && !\in_array($request->getPathInfo(), self::PAGES, true)) {
            $request->attributes->set('_stateless', true);
        }
    }
}
