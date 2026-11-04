<?php

declare(strict_types=1);

namespace CookieConsentManager\Controller\Admin;

use CookieConsentManager\Admin\ActiveLanguages;
use CookieConsentManager\Admin\ContrastChecker;
use CookieConsentManager\Admin\ContrastFinding;
use CookieConsentManager\Admin\DraftSectionEditor;
use CookieConsentManager\Admin\DraftWriter;
use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Front\FrontTexts;
use CookieConsentManager\Publication\VersionRepository;
use CookieConsentManager\Validation\DraftValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\Security\AccessManager;

#[Route(AbstractScreenController::BASE_PATH.'/appearance', name: 'ccm_admin_appearance')]
final class AppearanceController extends AbstractScreenController
{
    public function __construct(
        UrlGeneratorInterface $urls,
        DraftRepository $drafts,
        DraftWriter $writer,
        DraftSectionEditor $editor,
        VersionRepository $versions,
        private readonly ContrastChecker $contrast,
        private readonly ActiveLanguages $languages,
        private readonly DraftValidator $validator,
        private readonly FrontTexts $frontTexts,
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

    /** One form, one button: layout, colors and texts are saved together, or not at all. */
    #[Route('/colors', name: '_save', methods: ['POST'])]
    public function saveAppearance(Request $request): Response
    {
        if (null !== $denied = $this->guardWrite($request, AccessManager::UPDATE)) {
            return $denied;
        }

        $input = $request->request->all();
        $draft = $this->editor->texts($this->editor->appearance($this->drafts->load(), $input), $input);
        $locale = (string) $request->request->get('reset', '');
        if ('' !== $locale) {
            $draft = $this->editor->resetTexts($draft, $locale);
        }

        $result = $this->save($draft, ['general.layout', 'texts.'], true);
        if (!$result->saved) {
            return $this->screen($result->draft, $result->issues, $result->contrast, 422);
        }

        $this->flash($request, 'success', '' !== $locale ? $this->say('Appearance and texts saved in the draft. Default texts restored for "%locale%".', ['%locale%' => $locale]) : $this->say('Appearance and texts saved in the draft.'));

        return $this->redirectTo('ccm_admin_appearance');
    }

    /**
     * @param array<string, mixed>                                   $draft
     * @param list<\CookieConsentManager\Validation\ValidationIssue> $issues
     * @param list<ContrastFinding>|null                             $findings
     */
    private function screen(array $draft, array $issues = [], ?array $findings = null, int $status = 200): Response
    {
        $locales = array_values(array_unique([...$this->languages->codes(), ...array_keys($draft['texts'])]));
        $contrast = $findings ?? $this->contrast->check($draft['appearance']['colors']);

        return $this->view('appearance', 'appearance', [
            'draft' => $draft,
            'issues' => $issues,
            'contrast' => $contrast,
            'locales' => $locales,
            'active_locales' => $this->languages->codes(),
            'default_languages' => array_keys(FrontTexts::LOCALES),
            'defaults' => array_combine($locales, array_map($this->frontTexts->defaultTexts(...), $locales)),
            'text_limits' => DraftValidator::TEXT_LIMITS,
            'text_warnings' => $this->textWarnings($draft),
            'refused' => 422 === $status,
            'refused_for_contrast' => 422 === $status && ContrastChecker::blocking($contrast),
            'refused_for_fields' => 422 === $status && DraftValidator::hasErrors($issues),
        ], $status);
    }

    /**
     * @param array<string, mixed> $draft
     *
     * @return array<string, list<string>>
     */
    private function textWarnings(array $draft): array
    {
        $warnings = [];
        foreach ($this->validator->validate($draft) as $issue) {
            if (str_starts_with($issue->field, 'texts.') && !$issue->isError()) {
                $warnings[explode('.', $issue->field)[1]][] = $issue->message;
            }
        }

        return $warnings;
    }
}
