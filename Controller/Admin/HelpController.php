<?php

declare(strict_types=1);

namespace CookieConsentManager\Controller\Admin;

use CookieConsentManager\Admin\DraftSectionEditor;
use CookieConsentManager\Admin\DraftWriter;
use CookieConsentManager\Admin\GuideRenderer;
use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Publication\VersionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route(AbstractScreenController::BASE_PATH.'/help', name: 'ccm_admin_help')]
final class HelpController extends AbstractScreenController
{
    public function __construct(
        UrlGeneratorInterface $urls,
        DraftRepository $drafts,
        DraftWriter $writer,
        DraftSectionEditor $editor,
        VersionRepository $versions,
        private readonly GuideRenderer $guides,
    ) {
        parent::__construct($urls, $drafts, $writer, $editor, $versions);
    }

    #[Route('', name: '', methods: ['GET'])]
    public function show(Request $request): Response
    {
        if (null !== $denied = $this->guardRead()) {
            return $denied;
        }

        $guide = (string) $request->query->get('guide', 'administrateur');
        $guide = isset(GuideRenderer::GUIDES[$guide]) ? $guide : 'administrateur';
        $html = $this->guides->render($guide);

        return $this->view('help', 'dashboard', ['guide' => $guide, 'guides' => array_keys(GuideRenderer::GUIDES), 'html' => $html], null === $html ? 404 : 200);
    }
}
