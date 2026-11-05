<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Option `blockEmbeds`: the iframes of the media services that are active and published are served
 * as placeholders. Runs before the shared cache stores the page (priority -1026): the cached copy is
 * for visitors without a cookie, who have consented to nothing.
 *
 * The answers of a LiveComponent are rewritten too: the browser morphs them into the page, so an untouched
 * iframe would replace the placeholder (or be inserted) without any consent. The engine watches the nodes
 * added to the page and restores the iframe itself when the service is already granted.
 */
final readonly class IframeRewriteListener
{
    private const LIVE_COMPONENT_TYPE = 'application/vnd.live-component+html';

    public function __construct(
        private FrontConfigProvider $configs,
        private IframeRewriter $rewriter,
        private PlaceholderRenderer $placeholders,
        private FrontThemeActivator $theme,
    ) {
    }

    // A response that has not been prepared yet carries no Content-Type: the kernel sets text/html afterwards.
    private function isRewritable(Request $request, Response $response): bool
    {
        $type = $response->headers->get('Content-Type');

        if ($request->attributes->has('_live_component')) {
            return null !== $type && str_starts_with($type, self::LIVE_COMPONENT_TYPE);
        }

        return !$request->isXmlHttpRequest() && (null === $type || str_contains($type, 'text/html'));
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: 0)]
    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (
            !$event->isMainRequest()
            || $response instanceof StreamedResponse
            || 200 !== $response->getStatusCode()
            || str_starts_with($request->getPathInfo(), '/admin')
            || !$this->isRewritable($request, $response)
        ) {
            return;
        }

        $content = $response->getContent();
        if (false === $content || false === stripos($content, '<iframe')) {
            return;
        }

        $front = $this->configs->forRequest($request);
        if (null === $front || !($front->config['options']['blockEmbeds'] ?? false)) {
            return;
        }

        // A LiveComponent, an ESI fragment, a controller or a page of another module is not a page of the theme: the placeholder template is found through the active theme.
        if (!$this->placeholders->canRender()) {
            $this->theme->activate();
        }

        $rewritten = $this->rewriter->rewrite(
            $content,
            PlaceholderRenderer::activeMediaKeys($front),
            fn (string $key, string $src, array $attributes): string => $this->placeholders->render($front, $key, $src, $attributes),
        );

        if ($rewritten !== $content) {
            $response->setContent($rewritten);
        }
    }
}
