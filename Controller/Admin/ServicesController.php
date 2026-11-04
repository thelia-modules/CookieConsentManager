<?php

declare(strict_types=1);

namespace CookieConsentManager\Controller\Admin;

use CookieConsentManager\Admin\ActiveLanguages;
use CookieConsentManager\Admin\DraftSectionEditor;
use CookieConsentManager\Admin\DraftWriter;
use CookieConsentManager\Admin\MarkupSnippets;
use CookieConsentManager\Catalogue\CatalogueEntry;
use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Catalogue\CookieDurations;
use CookieConsentManager\Draft\DefaultSettings;
use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Draft\ServiceControllers;
use CookieConsentManager\Front\FrontTexts;
use CookieConsentManager\Proposed\ProposedServiceSynchronizer;
use CookieConsentManager\Publication\VersionRepository;
use CookieConsentManager\Validation\ExemptionWhitelist;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\Security\AccessManager;

#[Route(AbstractScreenController::BASE_PATH.'/services', name: 'ccm_admin_')]
final class ServicesController extends AbstractScreenController
{
    private const PAGE_SIZE = 40;

    public function __construct(
        UrlGeneratorInterface $urls,
        DraftRepository $drafts,
        DraftWriter $writer,
        DraftSectionEditor $editor,
        VersionRepository $versions,
        private readonly CatalogueReader $catalogue,
        private readonly MarkupSnippets $snippets,
        private readonly ProposedServiceSynchronizer $synchronizer,
        private readonly ServiceControllers $controllers,
        private readonly ActiveLanguages $languages,
        private readonly FrontTexts $frontTexts,
        private readonly CookieDurations $durations,
    ) {
        parent::__construct($urls, $drafts, $writer, $editor, $versions);
    }

    #[Route('', name: 'services', methods: ['GET'])]
    public function show(Request $request): Response
    {
        if (null !== $denied = $this->guardRead()) {
            return $denied;
        }

        if ($this->isGranted(AccessManager::UPDATE)) {
            $this->synchronizer->synchronize();
        }

        return $this->screen($request, $this->drafts->load());
    }

    #[Route('/purposes', name: 'purposes_save', methods: ['POST'])]
    public function savePurposes(Request $request): Response
    {
        return $this->write($request, 'purposes', fn (array $draft): array => $this->editor->purposes($draft, $request->request->all()), ['purposes'], 'Purposes saved in the draft.');
    }

    #[Route('/catalogue/{key}', name: 'service_save', methods: ['POST'], requirements: ['key' => '[a-z0-9_-]+'])]
    public function saveCatalogueService(Request $request, string $key): Response
    {
        return $this->write($request, 'service-'.$key, fn (array $draft): array => $this->editor->catalogueService($draft, $key, $request->request->all()), ['services.'.$key], 'Service "%key%" saved in the draft.', ['%key%' => $this->catalogue->find($key)->name ?? $key]);
    }

    #[Route('/custom', name: 'custom_save', methods: ['POST'])]
    public function saveCustomService(Request $request): Response
    {
        return $this->write($request, 'custom', fn (array $draft): array => $this->editor->customService($draft, $request->request->all()), ['services.'], 'Custom service saved in the draft.');
    }

    #[Route('/custom/{key}/delete', name: 'custom_delete', methods: ['POST'], requirements: ['key' => '[a-z0-9_-]+'])]
    public function deleteCustomService(Request $request, string $key): Response
    {
        return $this->write($request, 'custom', fn (array $draft): array => $this->editor->removeCustomService($draft, $key), ['services.'], 'Custom service removed from the draft.');
    }

    #[Route('/proposed/{key}', name: 'proposed_save', methods: ['POST'], requirements: ['key' => '[a-z0-9_-]+'])]
    public function saveProposedService(Request $request, string $key): Response
    {
        return $this->write($request, 'proposed', fn (array $draft): array => $this->editor->proposedService($draft, $key, $request->request->all()), ['services.'.$key], 'Service "%key%" saved in the draft.', ['%key%' => array_column($this->drafts->load()['services'], 'name', 'key')[$key] ?? $key]);
    }

    #[Route('/exempt', name: 'exempt_save', methods: ['POST'])]
    public function saveExempt(Request $request): Response
    {
        return $this->write($request, 'exempt', fn (array $draft): array => $this->editor->exemptMeasurement($draft, $request->request->all()), ['exempt_measurement'], 'Exempted measurement saved in the draft.');
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $apply
     * @param list<string>                                         $prefixes
     * @param array<string, string>                                $parameters
     */
    private function write(Request $request, string $anchor, callable $apply, array $prefixes, string $success, array $parameters = []): Response
    {
        if (null !== $denied = $this->guardWrite($request, AccessManager::UPDATE)) {
            return $denied;
        }

        $result = $this->save($apply($this->drafts->load()), $prefixes);
        if (!$result->saved) {
            return $this->screen($request, $result->draft, $result->issues, $anchor, 422);
        }

        $this->flash($request, 'success', $this->say($success, $parameters));

        return new \Symfony\Component\HttpFoundation\RedirectResponse($this->urls->generate('ccm_admin_services').'#'.$anchor);
    }

    /**
     * @param array<string, mixed>                                   $draft
     * @param list<\CookieConsentManager\Validation\ValidationIssue> $issues
     */
    private function screen(Request $request, array $draft, array $issues = [], ?string $failedAnchor = null, int $status = 200): Response
    {
        $experimental = $draft['general']['experimental_services'];
        $filters = [
            'status' => \in_array($request->query->get('status'), ['verified', 'imported', 'all'], true) ? (string) $request->query->get('status') : 'verified',
            'purpose' => (string) $request->query->get('purpose', ''),
            'q' => trim((string) $request->query->get('q', '')),
            'page' => max(1, (int) $request->query->get('page', 1)),
        ];
        if (!$experimental) {
            $filters['status'] = 'verified';
        }

        $configured = array_column($draft['services'], null, 'key');
        $entries = array_filter($this->catalogue->exposed($experimental), static function (CatalogueEntry $entry) use ($filters): bool {
            return match ($filters['status']) {
                'verified' => $entry->verified,
                'imported' => !$entry->verified,
                default => true,
            } && ('' === $filters['purpose'] || $entry->purpose === $filters['purpose'])
                && ('' === $filters['q'] || false !== stripos($entry->name.' '.$entry->key, $filters['q']));
        });
        uasort($entries, static fn (CatalogueEntry $a, CatalogueEntry $b): int => [!isset($configured[$a->key]), !$a->verified, $a->name] <=> [!isset($configured[$b->key]), !$b->verified, $b->name]);

        $total = \count($entries);
        $rows = [];
        foreach (\array_slice($entries, ($filters['page'] - 1) * self::PAGE_SIZE, self::PAGE_SIZE) as $entry) {
            $rows[] = ['entry' => $entry, 'service' => $configured[$entry->key] ?? null, 'markup' => $this->snippets->for($entry->key), 'twig' => $this->snippets->twigExample($entry->key, $this->say('Template of your theme: the block stays inert until the visitor consents.')), 'controller' => $this->controllers->effective(['key' => $entry->key, 'controller' => $configured[$entry->key]['controller'] ?? '']), 'controller_default' => $this->controllers->default($entry->key), 'known_durations' => $this->knownDurations($entry)];
        }

        $custom = array_values(array_filter($draft['services'], static fn (array $service): bool => isset($service['custom'])));
        $proposed = array_values(array_filter($draft['services'], static fn (array $service): bool => isset($service['module'])));
        $alreadyExempt = array_column($draft['exempt_measurement'], 'service');
        $analytics = array_values(array_filter($draft['services'], static fn (array $service): bool => (ExemptionWhitelist::allows($service['key']) && !isset($service['custom']) && !isset($service['module'])) || \in_array($service['key'], $alreadyExempt, true)));

        $purposeLocales = array_values(array_unique([...$this->languages->codes(), ...array_merge(...array_map(static fn (array $purpose): array => array_keys($purpose['label']), $draft['purposes']))]));
        $purposeDefaults = [];
        foreach ($draft['purposes'] as $purpose) {
            foreach ($purposeLocales as $locale) {
                $purposeDefaults[$purpose['code']][$locale] = $this->frontTexts->defaultPurpose($purpose['code'], $locale) ?? ['label' => '', 'description' => ''];
            }
        }

        return $this->view('services', 'services', [
            'draft' => $draft,
            'issues' => $issues,
            'failed_anchor' => $failedAnchor,
            'filters' => $filters,
            'rows' => $rows,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / self::PAGE_SIZE)),
            'purposes' => $draft['purposes'],
            'purpose_locales' => $purposeLocales,
            'purpose_defaults' => $purposeDefaults,
            'custom' => $custom,
            'proposed' => $proposed,
            'exempt_candidates' => $analytics,
            'exempt_allowed' => ExemptionWhitelist::SERVICES,
            'exempt_conditions' => DefaultSettings::EXEMPT_CONDITIONS,
            'exempt' => array_column($draft['exempt_measurement'], null, 'service'),
            'signals' => DefaultSettings::SIGNALS,
            'catalogue_count' => ['verified' => \count($this->catalogue->exposed(false)), 'all' => \count($this->catalogue->exposed(true))],
        ], $status);
    }

    /**
     * Placeholder of the lifetime fields: the duration the module already knows, in days.
     *
     * @return array<string, string>
     */
    private function knownDurations(CatalogueEntry $entry): array
    {
        $known = [];
        foreach ($this->durations->details($entry->key, $entry->cookies) as $cookie) {
            if (isset($cookie['duration_days'])) {
                $known[$cookie['name']] = (string) $cookie['duration_days'];
            } elseif (isset($cookie['duration_minutes'])) {
                $known[$cookie['name']] = $cookie['duration_minutes'].' min';
            }
        }

        return $known;
    }
}
