<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Controller\Admin\VersionsController;
use CookieConsentManager\Publication\ConfigDiff;
use CookieConsentManager\Tests\Support\CcmTestCase;
use CookieConsentManager\Transfer\ConfigExporter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Tools\TokenProvider;

/** An import and a restoration replace the whole draft: the server asks for the confirmation, the screen announces what is lost. */
final class DraftReplacementTest extends CcmTestCase
{
    private const WITH_CREATE = [AccessManager::VIEW, AccessManager::UPDATE, AccessManager::CREATE];

    private Request $request;

    /**
     * @param array<string, mixed>        $fields
     * @param callable(Request): Response $call
     */
    private function post(array $fields, callable $call, string $method = 'POST'): Response
    {
        $this->loginWithModuleRights(self::WITH_CREATE);
        $request = Request::create('http://localhost/admin/module/CookieConsentManager/x', $method, $fields);
        $request->setSession(static::getContainer()->get('request_stack')->getCurrentRequest()->getSession());
        if ('POST' === $method) {
            $request->request->set('_token', $this->getService(TokenProvider::class)->assignToken());
        }
        static::getContainer()->get('request_stack')->push($request);
        $this->request = $request;

        try {
            return $call($request);
        } finally {
            static::getContainer()->get('request_stack')->pop();
        }
    }

    /** @return list<string> */
    private function flashes(string $type): array
    {
        $session = $this->request->getSession();
        self::assertInstanceOf(FlashBagAwareSessionInterface::class, $session);
        $bag = $session->getFlashBag();
        self::assertInstanceOf(FlashBagInterface::class, $bag);

        return $bag->get($type);
    }

    public function testAnImportIsRefusedWithoutTheConfirmationAndLeavesTheDraftAlone(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'test');
        $before = $this->ccm->drafts->load();
        $document = $this->ccm->exporter->export();
        $document['general']['renewal_months'] = 9;
        $json = ConfigExporter::toJson($document);
        $controller = $this->getService(VersionsController::class);

        $refused = $this->post(['config_json' => $json], $controller->import(...));

        self::assertSame(302, $refused->getStatusCode());
        self::assertSame([], ConfigDiff::between($before, $this->ccm->drafts->load()), 'the draft was not replaced');
        self::assertSame(['Nothing was changed: tick the confirmation that the current draft will be replaced.'], $this->flashes('warning'));

        $accepted = $this->post(['config_json' => $json, 'confirm_replace' => '1'], $controller->import(...));

        self::assertSame(200, $accepted->getStatusCode());
        self::assertSame(9, $this->ccm->drafts->load()['general']['renewal_months'], 'with the confirmation the draft is replaced');
    }

    public function testARestorationIsRefusedWithoutTheConfirmationAndLeavesTheDraftAlone(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'test');
        $version = $this->ccm->publisher->publish('test')->getVersion();
        $edited = $this->ccm->drafts->load();
        $edited['general']['renewal_months'] = 7;
        $this->ccm->drafts->replace($edited, 'test');
        $controller = $this->getService(VersionsController::class);

        $refused = $this->post([], static fn (Request $request): Response => $controller->restore($request, $version));

        self::assertSame(302, $refused->getStatusCode());
        self::assertSame(7, $this->ccm->drafts->load()['general']['renewal_months'], 'the edited draft is still there');
        self::assertSame(['Nothing was changed: tick the confirmation that the current draft will be replaced.'], $this->flashes('warning'));

        $accepted = $this->post(['confirm_replace' => '1'], static fn (Request $request): Response => $controller->restore($request, $version));

        self::assertSame(302, $accepted->getStatusCode());
        self::assertNotSame(7, $this->ccm->drafts->load()['general']['renewal_months'], 'with the confirmation the version replaces the draft');
        self::assertSame([], $this->flashes('warning'));
    }

    public function testTheScreensAskForTheConfirmationAndSayWhatIsLost(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'test');
        $version = $this->ccm->publisher->publish('test')->getVersion();
        $controller = $this->getService(VersionsController::class);

        $clean = (string) $this->post([], static fn (Request $request): Response => $controller->list($request), 'GET')->getContent();
        self::assertStringContainsString('ccm-import-confirm', $clean);
        self::assertStringContainsString('ccm-replace-notice', $clean);
        self::assertStringNotContainsString('ccm-replace-unpublished', $clean, 'no unpublished change, nothing to warn about');

        $edited = $this->ccm->drafts->load();
        $edited['general']['renewal_months'] = 7;
        $this->ccm->drafts->replace($edited, 'test');

        $list = (string) $this->post([], static fn (Request $request): Response => $controller->list($request), 'GET')->getContent();
        self::assertStringContainsString('ccm-replace-unpublished', $list);
        $detail = (string) $this->post([], static fn (Request $request): Response => $controller->show($version), 'GET')->getContent();
        self::assertStringContainsString('ccm-restore-confirm', $detail);
        self::assertStringContainsString('ccm-replace-unpublished', $detail);
    }
}
