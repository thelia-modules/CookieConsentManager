<?php

declare(strict_types=1);

namespace CookieConsentManager\Controller\Admin;

use CookieConsentManager\Admin\CsvLabeler;
use CookieConsentManager\Admin\DraftSectionEditor;
use CookieConsentManager\Admin\DraftWriter;
use CookieConsentManager\Admin\RegistryBrowser;
use CookieConsentManager\Admin\RegistryFilters;
use CookieConsentManager\Admin\RegistryProof;
use CookieConsentManager\Admin\StatsPresenter;
use CookieConsentManager\Admin\StoredTime;
use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Publication\VersionRepository;
use CookieConsentManager\Registry\ConsentPayloadValidator;
use CookieConsentManager\Registry\EntryRepository;
use CookieConsentManager\Registry\LastPurgeStore;
use CookieConsentManager\Registry\RegistryCsvExporter;
use CookieConsentManager\Registry\RegistryOptions;
use CookieConsentManager\Registry\RegistryPurger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\Security\AccessManager;

#[Route(AbstractScreenController::BASE_PATH.'/registry', name: 'ccm_admin_registry')]
final class RegistryController extends AbstractScreenController
{
    public function __construct(
        UrlGeneratorInterface $urls,
        DraftRepository $drafts,
        DraftWriter $writer,
        DraftSectionEditor $editor,
        VersionRepository $versions,
        private readonly RegistryBrowser $browser,
        private readonly RegistryProof $proof,
        private readonly EntryRepository $entries,
        private readonly RegistryCsvExporter $csv,
        private readonly RegistryPurger $purger,
        private readonly LastPurgeStore $lastPurge,
        private readonly StatsPresenter $stats,
        private readonly CsvLabeler $csvLabeler,
        private readonly RegistryOptions $options,
    ) {
        parent::__construct($urls, $drafts, $writer, $editor, $versions);
    }

    #[Route('', name: '', methods: ['GET'])]
    public function list(Request $request): Response
    {
        if (null !== $denied = $this->guardRead()) {
            return $denied;
        }

        return $this->screen($request, $this->drafts->load());
    }

    #[Route('/export', name: '_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        if (null !== $denied = $this->guardRead()) {
            return $denied;
        }

        $filters = RegistryFilters::fromQuery($request->query->all());
        $locale = substr($request->getLocale(), 0, 2);
        $response = new StreamedResponse(function () use ($filters, $locale): void {
            $stream = fopen('php://output', 'w');
            if (false !== $stream) {
                $this->csv->write($stream, $filters->action, $filters->from, $filters->to, $this->csvLabeler, $locale);
                fclose($stream);
            }
        });
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="ccm-registry-'.gmdate('Ymd-His').'.csv"');
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/consent/{consentId}', name: '_consent', methods: ['GET'], requirements: ['consentId' => '[0-9a-fA-F-]{36}'])]
    public function consent(string $consentId): Response
    {
        if (null !== $denied = $this->guardRead()) {
            return $denied;
        }

        $entries = $this->entries->forConsentId(strtolower($consentId));
        if ([] === $entries) {
            return $this->pageNotFound();
        }

        return $this->view('registry-consent', 'registry', ['consent_id' => strtolower($consentId), 'entries' => $entries]);
    }

    #[Route('/entry/{id}/proof', name: '_proof', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function proof(int $id, Request $request): Response
    {
        if (null !== $denied = $this->guardRead()) {
            return $denied;
        }

        $entry = $this->browser->find($id);
        if (null === $entry) {
            return $this->pageNotFound();
        }

        return $this->view('registry-proof', 'registry', ['proof' => $this->proof->build($entry, substr($request->getLocale(), 0, 2)), 'generated_at' => StoredTime::describe(new \DateTimeImmutable('now', new \DateTimeZone('UTC')))]);
    }

    #[Route('/purge', name: '_purge', methods: ['POST'])]
    public function purge(Request $request): Response
    {
        if (null !== $denied = $this->guardWrite($request, AccessManager::DELETE)) {
            return $denied;
        }

        $deleted = $this->purger->purge();
        $this->flash($request, 'success', $this->say('%count% registry entries older than the retention were deleted.', ['%count%' => (string) $deleted]));

        return $this->redirectTo('ccm_admin_registry');
    }

    #[Route('/options', name: '_options_save', methods: ['POST'])]
    public function saveOptions(Request $request): Response
    {
        if (null !== $denied = $this->guardWrite($request, AccessManager::UPDATE)) {
            return $denied;
        }

        $result = $this->save($this->editor->registryOptions($this->drafts->load(), $request->request->all()), ['registry.']);
        if (!$result->saved) {
            return $this->screen($request, $result->draft, $result->issues, 422);
        }

        $this->flash($request, 'success', $this->say('Registry options saved in the draft: they apply from the next publication.'));

        return $this->redirectTo('ccm_admin_registry');
    }

    /**
     * @param array<string, mixed>                                   $draft
     * @param list<\CookieConsentManager\Validation\ValidationIssue> $issues
     */
    private function screen(Request $request, array $draft, array $issues = [], int $status = 200): Response
    {
        $filters = RegistryFilters::fromQuery($request->query->all());
        $page = $this->browser->search($filters);

        return $this->view('registry', 'registry', [
            'draft' => $draft,
            'issues' => $issues,
            'filters' => $filters,
            'query' => $filters->toQuery(),
            'page' => min($filters->page, $page['pages']),
            'rows' => $page['rows'],
            'total' => $page['total'],
            'pages' => $page['pages'],
            'actions' => ConsentPayloadValidator::ACTIONS,
            'sources' => ConsentPayloadValidator::SOURCES,
            'published_versions' => array_map(static fn ($version): int => $version->getVersion(), $this->versions->all()),
            'stats' => $this->stats->present((int) $request->query->get('days', 30)),
            'periods' => StatsPresenter::PERIODS,
            'in_force' => $this->options->current(),
            'cutoff' => $this->purger->cutoff(),
            'last_purge' => $this->lastPurge->read(),
            'purge_late' => $this->purger->isLate(),
            'purgeable' => $this->purger->purge(null, true),
        ], $status);
    }
}
