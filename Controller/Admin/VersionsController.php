<?php

declare(strict_types=1);

namespace CookieConsentManager\Controller\Admin;

use CookieConsentManager\Admin\ChangePresenter;
use CookieConsentManager\Admin\DraftSectionEditor;
use CookieConsentManager\Admin\DraftWriter;
use CookieConsentManager\Admin\FrontLinks;
use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Publication\PublicationRefusedException;
use CookieConsentManager\Publication\Publisher;
use CookieConsentManager\Publication\VersionRepository;
use CookieConsentManager\Publication\VersionRestorer;
use CookieConsentManager\Transfer\ConfigExporter;
use CookieConsentManager\Transfer\ConfigImporter;
use CookieConsentManager\Transfer\EnvironmentFields;
use CookieConsentManager\Transfer\ImportRefusedException;
use CookieConsentManager\Validation\DraftValidator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\Security\AccessManager;

#[Route(AbstractScreenController::BASE_PATH, name: 'ccm_admin_')]
final class VersionsController extends AbstractScreenController
{
    private const PAGE_SIZE = 25;

    public function __construct(
        UrlGeneratorInterface $urls,
        DraftRepository $drafts,
        DraftWriter $writer,
        DraftSectionEditor $editor,
        VersionRepository $versions,
        private readonly Publisher $publisher,
        private readonly VersionRestorer $restorer,
        private readonly ConfigExporter $exporter,
        private readonly ConfigImporter $importer,
        private readonly DraftValidator $validator,
        private readonly FrontLinks $links,
        private readonly EnvironmentFields $environmentFields,
        private readonly ChangePresenter $changes,
    ) {
        parent::__construct($urls, $drafts, $writer, $editor, $versions);
    }

    #[Route('/versions', name: 'versions', methods: ['GET'])]
    public function list(Request $request): Response
    {
        if (null !== $denied = $this->guardRead()) {
            return $denied;
        }

        return $this->screen([], 200, ['page' => max(1, $request->query->getInt('page', 1))]);
    }

    #[Route('/versions/export', name: 'versions_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        if (null !== $denied = $this->guardRead()) {
            return $denied;
        }

        $number = $request->query->getInt('version');
        $version = $number > 0 ? $this->versions->find($number) : null;
        if ($number > 0 && null === $version) {
            return $this->pageNotFound();
        }

        $document = null === $version
            ? $this->exporter->export()
            : $this->exporter->fromDraft($this->versions->snapshot($version)['config'], $version->getVersion());

        return $this->download(ConfigExporter::toJson($document), 'ccm-config-'.(null === $version ? 'draft' : 'v'.$version->getVersion()).'-'.gmdate('Ymd').'.json', 'application/json');
    }

    #[Route('/versions/publish', name: 'versions_publish', methods: ['POST'])]
    public function publish(Request $request): Response
    {
        if (null !== $denied = $this->guardWrite($request, AccessManager::CREATE)) {
            return $denied;
        }

        $comment = trim((string) $request->request->get('comment', ''));

        try {
            $version = $this->publisher->publish($this->author(), $request->request->getBoolean('substantial'), '' === $comment ? null : mb_substr($comment, 0, 255));
        } catch (PublicationRefusedException $refusal) {
            return $this->screen($refusal->issues, 422);
        }

        $this->flash($request, 'success', $this->say('Version %version% published.', ['%version%' => (string) $version->getVersion()]));

        return $this->redirectTo('ccm_admin_versions');
    }

    #[Route('/versions/import', name: 'versions_import', methods: ['POST'])]
    public function import(Request $request): Response
    {
        if (null !== $denied = $this->guardWrite($request, AccessManager::UPDATE)) {
            return $denied;
        }

        $file = $request->files->get('config_file');
        $json = $file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile ? (string) file_get_contents($file->getPathname()) : (string) $request->request->get('config_json', '');

        try {
            $report = $this->importer->import($json, $this->author());
        } catch (ImportRefusedException $refusal) {
            return $this->screen([], 422, ['import_errors' => $refusal->violations]);
        }

        $this->flash($request, 'success', $this->say('Configuration imported into the draft. Nothing is published: confirm the environment fields, then publish.'));

        return $this->screen([], 200, ['import_report' => $report]);
    }

    #[Route('/versions/environment', name: 'versions_environment', methods: ['POST'])]
    public function confirmEnvironment(Request $request): Response
    {
        if (null !== $denied = $this->guardWrite($request, AccessManager::UPDATE)) {
            return $denied;
        }

        $input = $request->request->all();
        $confirmed = array_values(array_filter((array) ($input['confirmed'] ?? []), 'is_string'));
        $values = array_filter((array) ($input['values'] ?? []), 'is_string');

        $outcome = $this->environmentFields->resolve($this->drafts->load(), $confirmed, $values);
        if ([] === $outcome['resolved']) {
            $this->flash($request, 'warning', $this->say('Nothing was confirmed: tick a field to keep its imported value, or type the value of this shop.'));

            return $this->redirectTo('ccm_admin_versions');
        }

        $result = $this->save($outcome['draft'], $outcome['resolved']);
        if (!$result->saved) {
            return $this->screen($result->issues, 422, ['typed' => $values]);
        }

        $this->flash($request, 'success', $this->say('%count% field(s) confirmed.', ['%count%' => (string) \count($outcome['resolved'])]));

        return $this->redirectTo('ccm_admin_versions');
    }

    #[Route('/versions/{number}', name: 'version_show', methods: ['GET'], requirements: ['number' => '\d+'])]
    public function show(int $number): Response
    {
        if (null !== $denied = $this->guardRead()) {
            return $denied;
        }

        $version = $this->versions->find($number);
        if (null === $version) {
            return $this->pageNotFound();
        }

        $snapshot = $this->versions->snapshot($version);
        $previous = $this->versions->find($number - 1);

        return $this->view('version', 'versions', [
            'version' => $version,
            'snapshot' => $snapshot,
            'snapshot_json' => json_encode($snapshot, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
            'diff' => null === $previous ? [] : $this->changes->present($this->versions->snapshot($previous)['config'], $snapshot['config'], $this->uiLocale()),
            'has_previous' => null !== $previous,
        ]);
    }

    #[Route('/versions/{number}/restore', name: 'version_restore', methods: ['POST'], requirements: ['number' => '\d+'])]
    public function restore(Request $request, int $number): Response
    {
        if (null !== $denied = $this->guardWrite($request, AccessManager::UPDATE)) {
            return $denied;
        }

        if (null === $this->versions->find($number)) {
            return $this->pageNotFound();
        }

        $this->restorer->restore($number, $this->author());
        $this->flash($request, 'success', $this->say('Version %version% restored in the draft. Publish to make it live.', ['%version%' => (string) $number]));

        return $this->redirectTo('ccm_admin_versions');
    }

    #[Route('/preview', name: 'preview', methods: ['GET'])]
    public function preview(Request $request): Response
    {
        if (null !== $denied = $this->guardRead()) {
            return $denied;
        }

        $path = (string) $request->query->get('path', '/');
        // A path of this site only: never a host, never a scheme-relative URL.
        if (1 !== preg_match('~^/(?!/)[^\s\\\\]*$~', $path)) {
            $path = '/';
        }

        return new RedirectResponse($this->links->preview($path));
    }

    /**
     * @param list<\CookieConsentManager\Validation\ValidationIssue> $issues
     * @param array<string, mixed>                                   $extra
     */
    private function screen(array $issues = [], int $status = 200, array $extra = []): Response
    {
        $draft = $this->drafts->load();
        $latest = $this->versions->latest();
        $diff = $this->changes->present(null === $latest ? [] : $this->versions->snapshot($latest)['config'], $draft, $this->uiLocale());
        $all = $this->versions->all();
        $pages = max(1, (int) ceil(\count($all) / self::PAGE_SIZE));
        $page = min($pages, (int) ($extra['page'] ?? 1));

        return $this->view('versions', 'versions', $extra + [
            'versions_list' => \array_slice($all, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE),
            'versions_total' => \count($all),
            'versions_page' => $page,
            'versions_pages' => $pages,
            'draft' => $draft,
            'issues' => [] === $issues ? array_values(array_filter($this->validator->validate($draft), static fn ($issue): bool => 'environment_unconfirmed' !== $issue->code)) : $issues,
            'diff' => $diff,
            'environment_pending' => (array) ($draft['_meta']['environment_pending'] ?? []),
            'env_fields' => $this->environmentFields->pending($draft),
        ], $status);
    }

    private function uiLocale(): string
    {
        return substr($this->requestStack->getCurrentRequest()?->getLocale() ?? 'en', 0, 2);
    }

    private function download(string $content, string $filename, string $type): Response
    {
        $response = new Response($content);
        $response->headers->set('Content-Type', $type.'; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
