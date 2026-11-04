<?php

declare(strict_types=1);

namespace CookieConsentManager\Controller\Admin;

use CookieConsentManager\Admin\ActiveLanguages;
use CookieConsentManager\Admin\ChangePresenter;
use CookieConsentManager\Admin\DraftSectionEditor;
use CookieConsentManager\Admin\DraftWriter;
use CookieConsentManager\Draft\DraftNormalizer;
use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Preset\Preset;
use CookieConsentManager\Preset\PresetApplier;
use CookieConsentManager\Preset\PresetCatalogue;
use CookieConsentManager\Publication\VersionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\Security\AccessManager;

#[Route(AbstractScreenController::BASE_PATH.'/presets', name: 'ccm_admin_presets')]
final class PresetsController extends AbstractScreenController
{
    private const SECTIONS = ['general.', 'texts.', 'purposes', 'consent_mode', 'registry.', 'exempt_measurement'];

    public function __construct(
        UrlGeneratorInterface $urls,
        DraftRepository $drafts,
        DraftWriter $writer,
        DraftSectionEditor $editor,
        VersionRepository $versions,
        private readonly PresetCatalogue $presets,
        private readonly PresetApplier $applier,
        private readonly DraftNormalizer $normalizer,
        private readonly ChangePresenter $changes,
        private readonly ActiveLanguages $languages,
    ) {
        parent::__construct($urls, $drafts, $writer, $editor, $versions);
    }

    #[Route('', name: '', methods: ['GET'])]
    public function show(): Response
    {
        if (null !== $denied = $this->guardRead()) {
            return $denied;
        }

        return $this->screen($this->drafts->load());
    }

    /** What the import would change, before anything is written. */
    #[Route('/preview', name: '_preview', methods: ['GET'])]
    public function preview(Request $request): Response
    {
        if (null !== $denied = $this->guardRead()) {
            return $denied;
        }

        $preset = $this->presets->find((string) $request->query->get('preset', ''));
        $language = (string) $request->query->get('language', '');
        if (null === $preset || !\in_array($language, $this->languages->codes(), true)) {
            $this->flash($request, 'warning', $this->say('Unknown preset or inactive language.'));

            return $this->redirectTo('ccm_admin_presets');
        }

        return $this->screen($this->drafts->load(), $preset, $language);
    }

    #[Route('/import', name: '_import', methods: ['POST'])]
    public function import(Request $request): Response
    {
        if (null !== $denied = $this->guardWrite($request, AccessManager::UPDATE)) {
            return $denied;
        }

        $preset = $this->presets->find((string) $request->request->get('preset', ''));
        $language = (string) $request->request->get('language', '');
        if (null === $preset || !\in_array($language, $this->languages->codes(), true)) {
            $this->flash($request, 'warning', $this->say('Unknown preset or inactive language.'));

            return $this->redirectTo('ccm_admin_presets');
        }

        $draft = $this->drafts->load();
        $result = $this->save($this->applier->apply($draft, $preset, $language, $language === $this->languages->mainCode()), self::SECTIONS);
        if (!$result->saved) {
            return $this->screen($draft, $preset, $language, $result->issues, 422);
        }

        $this->flash($request, 'success', $this->say('Preset "%preset%" imported into the draft for the language "%language%".', ['%preset%' => $preset->label, '%language%' => $language]));

        return $this->redirectTo('ccm_admin_presets');
    }

    /**
     * @param array<string, mixed>                                   $draft
     * @param list<\CookieConsentManager\Validation\ValidationIssue> $issues
     */
    private function screen(array $draft, ?Preset $preset = null, ?string $language = null, array $issues = [], int $status = 200): Response
    {
        $main = $this->languages->mainCode();
        $variables = [
            'draft' => $draft,
            'presets' => $this->presets->all(),
            'languages' => $this->languages->codes(),
            'main_language' => $main,
            'attached' => array_map(fn (string $code): ?Preset => $this->presets->find($code), $draft['presets']),
            'issues' => $issues,
        ];

        if (null !== $preset && null !== $language) {
            $candidate = $this->normalizer->normalize($this->applier->apply($draft, $preset, $language, $language === $main));
            $variables += [
                'preview' => $preset,
                'preview_language' => $language,
                'preview_main' => $language === $main,
                'preview_rows' => $this->changes->present($draft, $candidate, substr($this->requestStack->getCurrentRequest()?->getLocale() ?? 'en', 0, 2)),
            ];
        }

        return $this->view('presets', 'presets', $variables, $status);
    }
}
