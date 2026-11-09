<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Controller\Admin\DashboardController;
use CookieConsentManager\Controller\Admin\RegistryController;
use CookieConsentManager\Controller\Admin\ServicesController;
use CookieConsentManager\Controller\Admin\VersionsController;
use CookieConsentManager\Model\ConsentEntry;
use CookieConsentManager\Publication\ConfigDiff;
use CookieConsentManager\Tests\Support\CcmTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Tools\TokenProvider;

/** Whatever can put a script on the storefront needs the right that publishes; the registry export needs UPDATE. */
final class ScriptRightsTest extends CcmTestCase
{
    private const VIEW_UPDATE = [AccessManager::VIEW, AccessManager::UPDATE];
    private const WITH_CREATE = [AccessManager::VIEW, AccessManager::UPDATE, AccessManager::CREATE];

    /**
     * @param list<string>                $accesses
     * @param array<string, mixed>        $fields
     * @param callable(Request): Response $call
     */
    private function post(array $accesses, array $fields, callable $call, array $files = [], string $method = 'POST'): Response
    {
        $this->loginWithModuleRights($accesses);
        $request = Request::create('http://localhost/admin/module/CookieConsentManager/x', $method, $fields, [], $files);
        $request->setSession(static::getContainer()->get('request_stack')->getCurrentRequest()->getSession());
        if ('POST' === $method) {
            $request->request->set('_token', $this->getService(TokenProvider::class)->assignToken());
        }
        static::getContainer()->get('request_stack')->push($request);

        try {
            return $call($request);
        } finally {
            static::getContainer()->get('request_stack')->pop();
        }
    }

    private function customFields(string $code, string $scriptUrl = ''): array
    {
        return ['key' => 'chat_maison', 'name' => 'Chat', 'kind' => 'custom', 'purpose' => 'ads', 'controller' => 'Chat SAS', 'policy_url' => 'https://chat.test/p', 'code' => $code, 'script_url' => $scriptUrl];
    }

    private function services(): array
    {
        return array_column($this->ccm->drafts->load()['services'], null, 'key');
    }

    public function testSavingTheCodeOfACustomServiceNeedsThePublishRight(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'test');
        $controller = $this->getService(ServicesController::class);

        $denied = $this->post(self::VIEW_UPDATE, $this->customFields('window.x=1;'), $controller->saveCustomService(...));
        self::assertSame(403, $denied->getStatusCode());
        self::assertArrayNotHasKey('chat_maison', $this->services(), 'nothing was written');

        $deniedUrl = $this->post(self::VIEW_UPDATE, $this->customFields('', 'https://chat.test/a.js'), $controller->saveCustomService(...));
        self::assertSame(403, $deniedUrl->getStatusCode());

        $allowed = $this->post(self::WITH_CREATE, $this->customFields('window.x=1;'), $controller->saveCustomService(...));
        self::assertSame(302, $allowed->getStatusCode());
        self::assertSame('window.x=1;', $this->services()['chat_maison']['code']);
    }

    public function testEditingACustomServiceWithoutTouchingItsCodeNeedsOnlyUpdate(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'test');
        $controller = $this->getService(ServicesController::class);
        $this->post(self::WITH_CREATE, $this->customFields('window.x=1;'), $controller->saveCustomService(...));

        $response = $this->post(self::VIEW_UPDATE, ['name' => 'Chat renamed'] + $this->customFields('window.x=1;'), $controller->saveCustomService(...));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('Chat renamed', $this->services()['chat_maison']['name']);

        $changed = $this->post(self::VIEW_UPDATE, $this->customFields('window.y=2;'), $controller->saveCustomService(...));
        self::assertSame(403, $changed->getStatusCode());
        self::assertSame('window.x=1;', $this->services()['chat_maison']['code']);
    }

    /** @return array<string, mixed> */
    private function parametersOf(string $key): array
    {
        return $this->services()[$key]['parameters'] ?? [];
    }

    /** A draft with Matomo Tag Manager (experimental service, the address of its container is a script) and GA4. */
    private function draftWithExecutableParameter(string $value = ''): array
    {
        $draft = $this->publishableDraft();
        $draft['general']['experimental_services'] = true;
        $draft['services'][] = ['key' => 'matomotm', 'purpose' => 'analytics', 'enabled' => true, 'parameters' => '' === $value ? [] : ['matomotmUrl' => $value]];

        return $draft;
    }

    public function testChangingTheAddressOfAScriptParameterNeedsThePublishRight(): void
    {
        $this->ccm->drafts->replace($this->draftWithExecutableParameter('https://tm.example.test/container.js'), 'test');
        $controller = $this->getService(ServicesController::class);
        $fields = ['enabled' => '1', 'parameters' => ['matomotmUrl' => 'https://evil.example.test/x.js']];

        $denied = $this->post(self::VIEW_UPDATE, $fields, static fn (Request $request): Response => $controller->saveCatalogueService($request, 'matomotm'));
        self::assertSame(403, $denied->getStatusCode());
        self::assertSame('https://tm.example.test/container.js', $this->parametersOf('matomotm')['matomotmUrl'], 'nothing was written');

        $allowed = $this->post(self::WITH_CREATE, $fields, static fn (Request $request): Response => $controller->saveCatalogueService($request, 'matomotm'));
        self::assertSame(302, $allowed->getStatusCode());
        self::assertSame('https://evil.example.test/x.js', $this->parametersOf('matomotm')['matomotmUrl']);
    }

    public function testChangingATagManagerContainerNeedsThePublishRight(): void
    {
        $draft = $this->publishableDraft();
        $draft['services'][] = ['key' => 'googletagmanager', 'purpose' => 'ads', 'enabled' => true, 'parameters' => ['googletagmanagerId' => 'GTM-SHOP1']];
        $this->ccm->drafts->replace($draft, 'test');
        $controller = $this->getService(ServicesController::class);
        $fields = ['enabled' => '1', 'parameters' => ['googletagmanagerId' => 'GTM-OTHER2']];

        $denied = $this->post(self::VIEW_UPDATE, $fields, static fn (Request $request): Response => $controller->saveCatalogueService($request, 'googletagmanager'));
        self::assertSame(403, $denied->getStatusCode(), 'the container runs whatever its owner puts in it');
        self::assertSame('GTM-SHOP1', $this->parametersOf('googletagmanager')['googletagmanagerId']);

        $allowed = $this->post(self::WITH_CREATE, $fields, static fn (Request $request): Response => $controller->saveCatalogueService($request, 'googletagmanager'));
        self::assertSame(302, $allowed->getStatusCode());
        self::assertSame('GTM-OTHER2', $this->parametersOf('googletagmanager')['googletagmanagerId']);
    }

    public function testASetOfAScriptParameterFromNothingNeedsThePublishRightToo(): void
    {
        $this->ccm->drafts->replace($this->draftWithExecutableParameter(), 'test');
        $controller = $this->getService(ServicesController::class);

        $denied = $this->post(self::VIEW_UPDATE, ['enabled' => '1', 'parameters' => ['matomotmUrl' => 'https://tm.example.test/c.js']], static fn (Request $request): Response => $controller->saveCatalogueService($request, 'matomotm'));

        self::assertSame(403, $denied->getStatusCode());
        self::assertArrayNotHasKey('matomotmUrl', $this->parametersOf('matomotm'));
    }

    public function testAnIdentifierOfAnAccountAndAScriptParameterLeftAsItIsNeedOnlyUpdate(): void
    {
        $this->ccm->drafts->replace($this->draftWithExecutableParameter('https://tm.example.test/container.js'), 'test');
        $controller = $this->getService(ServicesController::class);

        $identifier = $this->post(self::VIEW_UPDATE, ['enabled' => '1', 'parameters' => ['gtagUa' => 'G-NEWACCOUNT1']], static fn (Request $request): Response => $controller->saveCatalogueService($request, 'gtag'));
        self::assertSame(302, $identifier->getStatusCode());
        self::assertSame('G-NEWACCOUNT1', $this->parametersOf('gtag')['gtagUa']);

        $untouched = $this->post(self::VIEW_UPDATE, ['parameters' => ['matomotmUrl' => 'https://tm.example.test/container.js']], static fn (Request $request): Response => $controller->saveCatalogueService($request, 'matomotm'));
        self::assertSame(302, $untouched->getStatusCode(), 'same address, service switched off');
        self::assertFalse($this->services()['matomotm']['enabled']);
    }

    public function testEmptyingAScriptParameterNeedsOnlyUpdate(): void
    {
        $this->ccm->drafts->replace($this->draftWithExecutableParameter('https://tm.example.test/container.js'), 'test');
        $controller = $this->getService(ServicesController::class);

        $response = $this->post(self::VIEW_UPDATE, ['parameters' => ['matomotmUrl' => '']], static fn (Request $request): Response => $controller->saveCatalogueService($request, 'matomotm'));

        self::assertSame(302, $response->getStatusCode());
        self::assertArrayNotHasKey('matomotmUrl', $this->parametersOf('matomotm'));
    }

    public function testTheSetupAssistantNeedsThePublishRightToSetAScriptParameter(): void
    {
        $draft = $this->publishableDraft();
        $draft['services'] = [];
        $this->ccm->drafts->replace($draft, 'test');
        $controller = $this->getService(DashboardController::class);
        $fields = ['step' => '2', 'keys' => ['matomo'], 'services' => ['matomo' => ['parameters' => ['matomoId' => '3', 'matomoHost' => 'https://stats.example.test/']]]];

        $denied = $this->post(self::VIEW_UPDATE, $fields, $controller->saveWizard(...));
        self::assertSame(403, $denied->getStatusCode());
        self::assertSame([], $this->ccm->drafts->load()['services'], 'nothing was written');

        $allowed = $this->post(self::WITH_CREATE, $fields, $controller->saveWizard(...));
        self::assertSame(302, $allowed->getStatusCode());
        self::assertSame('https://stats.example.test/', $this->parametersOf('matomo')['matomoHost']);
    }

    public function testTheSetupAssistantStillSetsAnIdentifierWithUpdate(): void
    {
        $draft = $this->publishableDraft();
        $draft['services'] = [];
        $this->ccm->drafts->replace($draft, 'test');
        $controller = $this->getService(DashboardController::class);

        $response = $this->post(self::VIEW_UPDATE, ['step' => '2', 'keys' => ['gtag'], 'services' => ['gtag' => ['parameters' => ['gtagUa' => 'G-ABC123XYZ']]]], $controller->saveWizard(...));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('G-ABC123XYZ', $this->parametersOf('gtag')['gtagUa']);
    }

    public function testTypingTheValueOfAPendingScriptParameterNeedsThePublishRight(): void
    {
        $draft = $this->draftWithExecutableParameter('https://tm.example.test/container.js');
        $draft['_meta']['environment_pending'] = ['services.matomotm.parameters.matomotmUrl', 'services.gtag.parameters.gtagUa'];
        $this->ccm->drafts->replace($draft, 'test');
        $controller = $this->getService(VersionsController::class);

        $denied = $this->post(self::VIEW_UPDATE, ['values' => ['services.matomotm.parameters.matomotmUrl' => 'https://evil.example.test/x.js']], $controller->confirmEnvironment(...));
        self::assertSame(403, $denied->getStatusCode());
        self::assertSame('https://tm.example.test/container.js', $this->parametersOf('matomotm')['matomotmUrl']);

        $confirmed = $this->post(self::VIEW_UPDATE, ['confirmed' => ['services.matomotm.parameters.matomotmUrl'], 'values' => ['services.gtag.parameters.gtagUa' => 'G-NEWACCOUNT1']], $controller->confirmEnvironment(...));
        self::assertSame(302, $confirmed->getStatusCode(), 'confirming the value as it stands, and typing an identifier, are UPDATE');
        self::assertSame('G-NEWACCOUNT1', $this->parametersOf('gtag')['gtagUa']);
    }

    public function testSwitchingExperimentalServicesOnNeedsThePublishRight(): void
    {
        $draft = $this->publishableDraft();
        $draft['general']['experimental_services'] = false;
        $this->ccm->drafts->replace($draft, 'test');
        $controller = $this->getService(DashboardController::class);

        $denied = $this->post(self::VIEW_UPDATE, ['general' => ['experimental_services' => '1'], 'policy' => ['url' => '/politique-de-confidentialite']], $controller->saveGeneral(...));
        self::assertSame(403, $denied->getStatusCode());
        self::assertFalse($this->ccm->drafts->load()['general']['experimental_services']);

        $allowed = $this->post(self::WITH_CREATE, ['general' => ['experimental_services' => '1'], 'policy' => ['url' => '/politique-de-confidentialite']], $controller->saveGeneral(...));
        self::assertSame(302, $allowed->getStatusCode());
        self::assertTrue($this->ccm->drafts->load()['general']['experimental_services']);
    }

    public function testImportingAConfigurationNeedsThePublishRight(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'test');
        $before = $this->ccm->drafts->load();
        $controller = $this->getService(VersionsController::class);

        $denied = $this->post(self::VIEW_UPDATE, ['config_json' => $this->completeConfigJson()], $controller->import(...));

        self::assertSame(403, $denied->getStatusCode());
        self::assertSame([], ConfigDiff::between($before, $this->ccm->drafts->load()));
    }

    public function testAnImportOverOneMegabyteOrAnInvalidUploadIsRefusedWithAMessage(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'test');
        $before = $this->ccm->drafts->load();
        $controller = $this->getService(VersionsController::class);

        $big = tempnam(sys_get_temp_dir(), 'ccm');
        file_put_contents($big, $this->completeConfigJson().str_repeat(' ', 1048576));
        $invalid = tempnam(sys_get_temp_dir(), 'ccm');
        file_put_contents($invalid, $this->completeConfigJson());

        foreach ([new UploadedFile($big, 'big.json', null, \UPLOAD_ERR_OK, true), new UploadedFile($invalid, 'x.json', null, \UPLOAD_ERR_INI_SIZE, true)] as $file) {
            $response = $this->post(self::WITH_CREATE, ['confirm_replace' => '1'], $controller->import(...), ['config_file' => $file]);
            self::assertSame(302, $response->getStatusCode());
            self::assertSame([], ConfigDiff::between($before, $this->ccm->drafts->load()));
        }
        unlink($big);
        unlink($invalid);
    }

    public function testRestoringAVersionNeedsThePublishRight(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'test');
        $version = $this->ccm->publisher->publish('test')->getVersion();
        $controller = $this->getService(VersionsController::class);

        $denied = $this->post(self::VIEW_UPDATE, [], static fn (Request $request): Response => $controller->restore($request, $version));
        $allowed = $this->post(self::WITH_CREATE, ['confirm_replace' => '1'], static fn (Request $request): Response => $controller->restore($request, $version));

        self::assertSame(403, $denied->getStatusCode());
        self::assertSame(302, $allowed->getStatusCode());
    }

    public function testTheRegistryExportAndTheProofSheetNeedUpdate(): void
    {
        $controller = $this->getService(RegistryController::class);

        $viewOnly = [AccessManager::VIEW];
        self::assertSame(403, $this->post($viewOnly, [], $controller->export(...), [], 'GET')->getStatusCode());
        self::assertSame(403, $this->post($viewOnly, [], static fn (Request $request): Response => $controller->proof(1, $request), [], 'GET')->getStatusCode());
        self::assertSame(200, $this->post(self::VIEW_UPDATE, [], $controller->export(...), [], 'GET')->getStatusCode());
        self::assertNotSame(403, $this->post(self::VIEW_UPDATE, [], static fn (Request $request): Response => $controller->proof(1, $request), [], 'GET')->getStatusCode());
    }

    public function testTheEntriesOfTheRegistryNeedUpdateWhileItsSettingsAndStatisticsStayReadable(): void
    {
        $consentId = '4f1c2b9e-8d7a-4c3e-9b1f-2a6d5e7c8b90';
        (new ConsentEntry())
            ->setConsentId($consentId)->setRecordedAt(new \DateTimeImmutable('2026-10-30 10:00:00', new \DateTimeZone('UTC')))
            ->setAction('custom')->setSource('banner')->setVersion(1)->setVersionHash('abcdef012345')
            ->setLocale('fr_FR')->setPath('/marker-visited-page')->setBrowser('Chrome 130')->setPurposesJson('{}')->setServicesJson('{}')
            ->save();
        $controller = $this->getService(RegistryController::class);
        $list = static fn (Request $request): Response => $controller->list($request);
        $detail = static fn (Request $request): Response => $controller->consent($consentId);

        $viewOnly = $this->post([AccessManager::VIEW], ['q' => '4f1c2b9e'], $list, [], 'GET');
        self::assertSame(200, $viewOnly->getStatusCode(), 'the screen stays open');
        $html = (string) $viewOnly->getContent();
        self::assertStringContainsString('ccm-registry-options-card', $html, 'the settings stay');
        self::assertStringContainsString('ccm-stats-card', $html, 'the aggregated statistics stay');
        self::assertStringContainsString('ccm-registry-restricted', $html, 'the screen says why there is no entry');
        foreach (['4f1c2b9e', 'marker-visited-page', 'ccm-registry-table', 'ccm-registry-filters', 'ccm-registry-total'] as $leak) {
            self::assertStringNotContainsString($leak, $html, $leak);
        }
        self::assertSame(403, $this->post([AccessManager::VIEW], [], $detail, [], 'GET')->getStatusCode());

        $updater = $this->post(self::VIEW_UPDATE, ['q' => '4f1c2b9e'], $list, [], 'GET');
        self::assertSame(200, $updater->getStatusCode());
        self::assertStringContainsString('marker-visited-page', (string) $updater->getContent());
        self::assertStringNotContainsString('ccm-registry-restricted', (string) $updater->getContent());
        $opened = $this->post(self::VIEW_UPDATE, [], $detail, [], 'GET');
        self::assertSame(200, $opened->getStatusCode());
        self::assertStringContainsString($consentId, (string) $opened->getContent());
    }
}
