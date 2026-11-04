<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Option `blockEmbeds`: the iframes of the media services that are active and published are served
 * as placeholders. Runs before the shared cache stores the page (priority -1026): the cached copy is
 * for visitors without a cookie, who have consented to nothing.
 */
final readonly class IframeRewriteListener
{
    public function __construct(
        private FrontConfigProvider $configs,
        private IframeRewriter $rewriter,
        private PlaceholderRenderer $placeholders,
    ) {
    }

    // A response that has not been prepared yet carries no Content-Type: the kernel sets text/html afterwards.
    private function isHtml(Response $response): bool
    {
        $type = $response->headers->get('Content-Type');

        return null === $type || str_contains($type, 'text/html');
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
            || $request->isXmlHttpRequest()
            || str_starts_with($request->getPathInfo(), '/admin')
            || !$this->isHtml($response)
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
