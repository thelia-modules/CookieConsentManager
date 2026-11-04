<?php

declare(strict_types=1);

namespace CookieConsentManager\Controller\Admin;

use CookieConsentManager\Admin\ActiveLanguages;
use CookieConsentManager\Admin\AlertCollector;
use CookieConsentManager\Admin\DraftSectionEditor;
use CookieConsentManager\Admin\DraftWriter;
use CookieConsentManager\Admin\ProfileRights;
use CookieConsentManager\Admin\StatsPresenter;
use CookieConsentManager\Catalogue\CatalogueEntry;
use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Publication\PublicationRefusedException;
use CookieConsentManager\Publication\Publisher;
use CookieConsentManager\Publication\VersionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\Security\AccessManager;

#[Route(AbstractScreenController::BASE_PATH, name: 'ccm_admin_')]
final class DashboardController extends AbstractScreenController
{
    public function __construct(
        UrlGeneratorInterface $urls,
        DraftRepository $drafts,
        DraftWriter $writer,
        DraftSectionEditor $editor,
        VersionRepository $versions,
        private readonly AlertCollector $alerts,
        private readonly StatsPresenter $stats,
        private readonly ProfileRights $rights,
        private readonly CatalogueReader $catalogue,
        private readonly Publisher $publisher,
        private readonly ActiveLanguages $languages,
    ) {
        parent::__construct($urls, $drafts, $writer, $editor, $versions);
    }

    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        return $this->dashboardView($request, $this->drafts->load());
    }

    #[Route('/general', name: 'general_save', methods: ['POST'])]
    public function saveGeneral(Request $request): Response
    {
        if (null !== $denied = $this->guardWrite($request, AccessManager::UPDATE)) {
            return $denied;
        }

        $result = $this->save($this->editor->general($this->drafts->load(), $request->request->all()), ['general.', 'policy.']);
        if (!$result->saved) {
            return $this->dashboardView($request, $result->draft, $result->issues, 422);
        }

        $this->flash($request, 'success', $this->say('General settings saved in the draft.'));

        return $this->redirectTo('ccm_admin_dashboard');
    }

    #[Route('/wizard', name: 'wizard', methods: ['GET'])]
    public function wizard(Request $request): Response
    {
        if (null !== $denied = $this->guardRead()) {
            return $denied;
        }

        return $this->wizardView($request, $this->drafts->load());
    }

    #[Route('/wizard', name: 'wizard_save', methods: ['POST'])]
    public function saveWizard(Request $request): Response
    {
        $step = max(1, min(4, (int) $request->request->get('step', 1)));
        // Step 4 publishes: the right comes from the step, never from a field the browser sends.
        if (null !== $denied = $this->guardWrite($request, 4 === $step ? AccessManager::CREATE : AccessManager::UPDATE)) {
            return $denied;
        }

        $draft = $this->drafts->load();

        if (1 === $step) {
            $keys = array_values(array_filter($request->request->all('keys'), fn (mixed $key): bool => \is_string($key) && true === $this->catalogue->find($key)?->verified));
            if ([] === $keys) {
                $this->flash($request, 'warning', $this->say('Pick at least one service to continue.'));

                return $this->redirectTo('ccm_admin_wizard', ['step' => 1]);
            }

            return $this->redirectTo('ccm_admin_wizard', ['step' => 2, 'keys' => $keys]);
        }

        if (2 === $step) {
            $keys = array_values(array_filter($request->request->all('keys'), 'is_string'));
            $services = (array) ($request->request->all()['services'] ?? []);
            foreach ($keys as $key) {
                $row = (array) ($services[$key] ?? []);
                $row['enabled'] = '1';
                $draft = $this->editor->catalogueService($draft, $key, $row);
            }
            $result = $this->save($draft, ['services.']);
            if (!$result->saved) {
                return $this->wizardView($request, $result->draft, $result->issues, 422, 2, $keys);
            }

            return $this->redirectTo('ccm_admin_wizard', ['step' => 3]);
        }

        if (3 === $step) {
            $result = $this->save($this->editor->general($draft, ['policy' => $request->request->all()['policy'] ?? []] + ['general' => $this->flagsOf($draft)]), ['policy.']);
            if (!$result->saved) {
                return $this->wizardView($request, $result->draft, $result->issues, 422, 3);
            }

            return $this->redirectTo('ccm_admin_wizard', ['step' => 4]);
        }

        try {
            $version = $this->publisher->publish($this->author(), false, $this->say('Published from the setup assistant.'));
        } catch (PublicationRefusedException $refusal) {
            return $this->wizardView($request, $draft, $refusal->issues, 422, 4);
        }
        $this->flash($request, 'success', $this->say('Version %version% published.', ['%version%' => (string) $version->getVersion()]));

        return $this->redirectTo('ccm_admin_versions');
    }

    /**
     * @param array<string, mixed>                                   $draft
     * @param list<\CookieConsentManager\Validation\ValidationIssue> $issues
     */
    private function dashboardView(Request $request, array $draft, array $issues = [], int $status = 200): Response
    {
        if (null !== $denied = $this->guardRead()) {
            return $denied;
        }

        $locale = $request->getLocale();

        return $this->view('dashboard', 'dashboard', [
            'draft' => $draft,
            'issues' => $issues,
            'alerts' => $this->alerts->collect($draft),
            'stats' => $this->stats->present((int) $request->query->get('days', 30)),
            'periods' => StatsPresenter::PERIODS,
            'rights' => $this->rights->rows($locale),
            'preview_url' => $this->urls->generate('ccm_admin_preview'),
            'policy_languages' => array_values(array_unique([...$this->languages->codes(), ...array_keys($draft['policy']['urls'])])),
        ], $status);
    }

    /**
     * @param array<string, mixed>                                   $draft
     * @param list<\CookieConsentManager\Validation\ValidationIssue> $issues
     * @param list<string>|null                                      $keys
     */
    private function wizardView(Request $request, array $draft, array $issues = [], int $status = 200, ?int $step = null, ?array $keys = null): Response
    {
        $step ??= max(1, min(4, (int) $request->query->get('step', 1)));
        $keys ??= array_values(array_filter((array) ($request->query->all()['keys'] ?? []), fn (mixed $key): bool => \is_string($key) && null !== $this->catalogue->find($key)));

        $verified = array_values(array_filter($this->catalogue->exposed(false), static fn (CatalogueEntry $entry): bool => $entry->verified));
        usort($verified, static fn (CatalogueEntry $a, CatalogueEntry $b): int => [$a->purpose, $a->name] <=> [$b->purpose, $b->name]);

        return $this->view('wizard', 'dashboard', [
            'draft' => $draft,
            'issues' => $issues,
            'step' => $step,
            'keys' => $keys,
            'verified' => $verified,
            'selected' => array_column(array_filter($draft['services'], static fn (array $service): bool => $service['enabled']), 'key'),
            'chosen' => array_values(array_filter(array_map(fn (string $key): ?CatalogueEntry => $this->catalogue->find($key), $keys))),
            'services' => array_column($draft['services'], null, 'key'),
            'preview_url' => $this->urls->generate('ccm_admin_preview'),
        ], $status);
    }

    /** @return array<string, string> the general flags as they stand, so that saving the policy leaves them unchanged */
    private function flagsOf(array $draft): array
    {
        $flags = [];
        foreach ($draft['general'] as $name => $value) {
            if (true === $value) {
                $flags[$name] = '1';
            } elseif (!\is_bool($value)) {
                $flags[$name] = (string) $value;
            }
        }

        return $flags;
    }
}
