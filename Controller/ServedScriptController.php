<?php

declare(strict_types=1);

namespace CookieConsentManager\Controller;

use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Front\FrontUrls;
use CookieConsentManager\Front\PreviewToken;
use CookieConsentManager\Front\ServedHashResolver;
use CookieConsentManager\Front\ServedScriptAssembler;
use CookieConsentManager\Publication\SnapshotAssembler;
use CookieConsentManager\Publication\VersionRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ServedScriptController
{
    public const ROUTE_SCRIPT = 'cookieconsentmanager_script';
    public const ROUTE_PREVIEW = 'cookieconsentmanager_preview_script';

    public function __construct(
        private VersionRepository $versions,
        private ServedHashResolver $hashes,
        private ServedScriptAssembler $assembler,
        private SnapshotAssembler $snapshots,
        private DraftRepository $drafts,
        private PreviewToken $tokens,
        private FrontUrls $urls,
    ) {
    }

    // Versioned by number and hash: whoever changes the script changes the URL, so it never expires.
    #[Route('/ccm/{version}-{hash}.js', name: self::ROUTE_SCRIPT, requirements: ['version' => '\d{1,9}', 'hash' => '[0-9a-f]{12}'], methods: ['GET'], stateless: true)]
    public function script(int $version, string $hash): Response
    {
        $published = $this->versions->find($version);
        if (null === $published) {
            throw new NotFoundHttpException();
        }

        // A page cached before an update of the module or of a theme still points to the previous hash: its buttons must not die, so it gets the current script of the same version.
        $current = $this->hashes->forVersion($published);
        if (!hash_equals($current, $hash)) {
            $redirect = new RedirectResponse($this->urls->script($version, $current), Response::HTTP_FOUND);
            $redirect->headers->set('Cache-Control', 'public, max-age=60');

            return $redirect;
        }

        $response = $this->response($this->assembler->assemble($this->versions->snapshot($published)));
        $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');

        return $response;
    }

    #[Route('/ccm/preview-{token}.js', name: self::ROUTE_PREVIEW, requirements: ['token' => PreviewToken::PATTERN], methods: ['GET'], stateless: true)]
    public function preview(Request $request, string $token): Response
    {
        // Stateless route: the administrator session cannot be read here, the proof set on the page does the binding.
        $draft = $this->drafts->load();
        if (!$this->tokens->isValid($token, $draft) || !$this->tokens->proofIsValid($token, $request->cookies->get(PreviewToken::PROOF_COOKIE))) {
            throw new NotFoundHttpException();
        }

        $response = $this->response($this->assembler->assemble($this->snapshots->assemble($draft)));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function response(string $script): Response
    {
        return new Response($script, 200, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
