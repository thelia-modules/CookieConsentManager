<?php

declare(strict_types=1);

namespace CookieConsentManager\Controller\Admin;

use CookieConsentManager\Admin\ActiveLanguages;
use CookieConsentManager\Admin\DraftSectionEditor;
use CookieConsentManager\Admin\DraftWriter;
use CookieConsentManager\Admin\PolicyPageTemplate;
use CookieConsentManager\Admin\ShopIdentity;
use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Front\FrontTexts;
use CookieConsentManager\Publication\VersionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route(AbstractScreenController::BASE_PATH.'/policy-page', name: 'ccm_admin_policy_template')]
final class PolicyTemplateController extends AbstractScreenController
{
    public function __construct(
        UrlGeneratorInterface $urls,
        DraftRepository $drafts,
        DraftWriter $writer,
        DraftSectionEditor $editor,
        VersionRepository $versions,
        private readonly PolicyPageTemplate $template,
        private readonly ActiveLanguages $languages,
        private readonly ShopIdentity $identity,
    ) {
        parent::__construct($urls, $drafts, $writer, $editor, $versions);
    }

    #[Route('', name: '', methods: ['GET'])]
    public function show(Request $request): Response
    {
        if (null !== $denied = $this->guardRead()) {
            return $denied;
        }

        $codes = $this->languages->codes();
        $language = (string) $request->query->get('lang', $this->languages->mainCode());
        $language = \in_array($language, $codes, true) ? $language : $this->languages->mainCode();
        $latest = $this->versions->latest();
        $identity = $this->identity->read();

        return $this->view('policy-template', 'dashboard', [
            'languages' => $codes,
            'language' => $language,
            'supported' => FrontTexts::supports($language),
            'html' => null === $latest ? null : $this->template->render($this->versions->snapshot($latest), $language),
            'published' => $latest,
            'identity_missing' => '' === $identity['name'] || '' === $identity['address'] || '' === $identity['email'],
        ]);
    }
}
