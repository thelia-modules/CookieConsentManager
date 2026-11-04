<?php

declare(strict_types=1);

namespace CookieConsentManager\Controller;

use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Front\PreviewToken;
use CookieConsentManager\Front\ServedHashResolver;
use CookieConsentManager\Front\ServedScriptAssembler;
use CookieConsentManager\Publication\SnapshotAssembler;
use CookieConsentManager\Publication\VersionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ServedScriptController
{
    public function __construct(
        private VersionRepository $versions,
        private ServedHashResolver $hashes,
        private ServedScriptAssembler $assembler,
        private SnapshotAssembler $snapshots,
        private DraftRepository $drafts,
        private PreviewToken $tokens,
    ) {
    }

    // Versioned by number and hash: whoever changes the script changes the URL, so it never expires.
    #[Route('/ccm/{version}-{hash}.js', name: 'cookieconsentmanager_script', requirements: ['version' => '\d+', 'hash' => '[0-9a-f]{12}'], methods: ['GET'], stateless: true)]
    public function script(int $version, string $hash): Response
    {
        $published = $this->versions->find($version);
        if (null === $published || !hash_equals($this->hashes->forVersion($published), $hash)) {
            throw new NotFoundHttpException();
        }

        $response = $this->response($this->assembler->assemble($this->versions->snapshot($published)));
        $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');

        return $response;
    }

    #[Route('/ccm/preview-{token}.js', name: 'cookieconsentmanager_preview_script', requirements: ['token' => PreviewToken::PATTERN], methods: ['GET'], stateless: true)]
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
