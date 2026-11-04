<?php

declare(strict_types=1);

namespace CookieConsentManager\Controller\Admin;

use CookieConsentManager\Draft\DefaultSettings;
use CookieConsentManager\Validation\ValidationIssue;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Core\Security\AccessManager;

#[Route(AbstractScreenController::BASE_PATH.'/consent-mode', name: 'ccm_admin_consent_mode')]
final class ConsentModeController extends AbstractScreenController
{
    #[Route('', name: '', methods: ['GET'])]
    public function show(): Response
    {
        if (null !== $denied = $this->guardRead()) {
            return $denied;
        }

        return $this->screen($this->drafts->load());
    }

    #[Route('/save', name: '_save', methods: ['POST'])]
    public function saveConsentMode(Request $request): Response
    {
        if (null !== $denied = $this->guardWrite($request, AccessManager::UPDATE)) {
            return $denied;
        }

        $stored = $this->drafts->load();
        $input = $request->request->all();
        $switching = 'advanced' === ($input['consent_mode']['mode'] ?? null) && 'advanced' !== $stored['consent_mode']['mode'];
        if ($switching && !$request->request->getBoolean('advanced_confirm')) {
            $candidate = $this->editor->consentMode($stored, $input);

            return $this->screen($candidate, [new ValidationIssue(ValidationIssue::ERROR, 'advanced_unconfirmed', 'consent_mode.mode', $this->say('Tick the confirmation to switch to the advanced mode.'))], 422, $stored['consent_mode']['mode']);
        }

        $result = $this->save($this->editor->consentMode($stored, $input), ['consent_mode']);
        if (!$result->saved) {
            return $this->screen($result->draft, $result->issues, 422);
        }

        $this->flash($request, 'success', $this->say('Consent Mode saved in the draft.'));

        return $this->redirectTo('ccm_admin_consent_mode');
    }

    /**
     * @param array<string, mixed>  $draft
     * @param list<ValidationIssue> $issues
     */
    private function screen(array $draft, array $issues = [], int $status = 200, ?string $storedMode = null): Response
    {
        return $this->view('consent-mode', 'consent_mode', [
            'draft' => $draft,
            'issues' => $issues,
            'stored_mode' => $storedMode ?? $draft['consent_mode']['mode'],
            'signals' => DefaultSettings::SIGNALS,
            'region_rows' => [...$draft['consent_mode']['regions'], ['iso' => [], 'default' => []], ['iso' => [], 'default' => []]],
        ], $status);
    }
}
