<?php

declare(strict_types=1);

namespace CookieConsentManager\Controller\Admin;

use CookieConsentManager\Admin\DraftSectionEditor;
use CookieConsentManager\Admin\DraftWriter;
use CookieConsentManager\Admin\FrontLinks;
use CookieConsentManager\Admin\StoredTime;
use CookieConsentManager\Admin\VerificationAnalyzer;
use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Publication\VersionRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\Security\AccessManager;

#[Route(AbstractScreenController::BASE_PATH.'/verification', name: 'ccm_admin_verification')]
final class VerificationController extends AbstractScreenController
{
    private const MAX_REPORTS = 40;

    public function __construct(
        UrlGeneratorInterface $urls,
        DraftRepository $drafts,
        DraftWriter $writer,
        DraftSectionEditor $editor,
        VersionRepository $versions,
        private readonly FrontLinks $links,
        private readonly VerificationAnalyzer $analyzer,
    ) {
        parent::__construct($urls, $drafts, $writer, $editor, $versions);
    }

    #[Route('', name: '', methods: ['GET'])]
    public function show(Request $request): Response
    {
        if (null !== $denied = $this->guardRead()) {
            return $denied;
        }

        $latest = $this->versions->latest();
        $services = [];
        $snapshot = null === $latest ? ['services_resolved' => []] : $this->versions->snapshot($latest);
        $exemptKeys = array_column((array) ($snapshot['config']['exempt_measurement'] ?? []), 'service');
        foreach ($snapshot['services_resolved'] as $service) {
            $services[] = ['key' => $service['key'], 'name' => $service['name'], 'purpose' => $service['purpose'], 'domains' => VerificationAnalyzer::serviceDomains($service, \in_array($service['key'], $exemptKeys, true))];
        }

        return $this->view('verification', 'versions', [
            'presets' => $this->links->auditPresets($request->getLocale()),
            'audit_token' => $this->links->token(),
            'services' => $services,
            'site_origin' => $request->getSchemeAndHttpHost(),
        ]);
    }

    #[Route('/analyse', name: '_analyse', methods: ['POST'])]
    public function analyse(Request $request): Response
    {
        if (null !== $denied = $this->guardWrite($request, AccessManager::VIEW)) {
            return $denied;
        }

        $latest = $this->versions->latest();
        if (null === $latest) {
            return new JsonResponse(['error' => 'no_published_version'], Response::HTTP_CONFLICT);
        }

        $reports = json_decode((string) $request->request->get('reports', '[]'), true);
        if (!\is_array($reports) || \count($reports) > self::MAX_REPORTS || !array_is_list($reports)) {
            return new JsonResponse(['error' => 'invalid_reports'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse($this->analyzer->analyze(array_values(array_filter($reports, 'is_array')), $this->versions->snapshot($latest), $request->getHost(), $latest->getVersion()));
    }

    #[Route('/report', name: '_report', methods: ['POST'])]
    public function report(Request $request): Response
    {
        if (null !== $denied = $this->guardWrite($request, AccessManager::VIEW)) {
            return $denied;
        }

        $latest = $this->versions->latest();
        $reports = json_decode((string) $request->request->get('reports', '[]'), true);
        if (null === $latest || !\is_array($reports) || \count($reports) > self::MAX_REPORTS || !array_is_list($reports)) {
            return new Response('', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $reports = array_values(array_filter($reports, 'is_array'));
        $result = $this->analyzer->analyze($reports, $this->versions->snapshot($latest), $request->getHost(), $latest->getVersion());
        $pages = array_values(array_unique(array_map(static fn (array $report): string => VerificationAnalyzer::cleanUrl((string) ($report['url'] ?? '')), $reports)));
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if ('json' !== $request->request->get('format')) {
            return $this->render('CookieConsentManager/verification-report', [
                'result' => $result,
                'pages' => $pages,
                'site_host' => $request->getHttpHost(),
                'version' => $latest->getVersion(),
                'served_hash' => $latest->getServedHash(),
                'generated' => StoredTime::describe($now),
                'reasons' => array_values(array_unique(array_filter(array_column($result['domains'], 'reason')))),
                'ui_locale' => substr($request->getLocale(), 0, 2),
            ]);
        }

        $document = [
            'generated_at' => $now->format('Y-m-d\TH:i:s\Z'),
            'site' => $request->getSchemeAndHttpHost(),
            'version' => $latest->getVersion(),
            'served_hash' => $latest->getServedHash(),
            'pages' => $pages,
            'result' => $result,
        ];

        $response = new Response(json_encode($document, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n");
        $response->headers->set('Content-Type', 'application/json; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="ccm-verification-'.gmdate('Ymd-His').'.json"');

        return $response;
    }
}
