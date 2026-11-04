<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Controller\Admin\DashboardController;
use CookieConsentManager\Controller\Admin\RegistryController;
use CookieConsentManager\Controller\Admin\ServicesController;
use CookieConsentManager\Controller\Admin\VersionsController;
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
            $response = $this->post(self::WITH_CREATE, [], $controller->import(...), ['config_file' => $file]);
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
        $allowed = $this->post(self::WITH_CREATE, [], static fn (Request $request): Response => $controller->restore($request, $version));

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
        self::assertNotSame(403, $this->post($viewOnly, [], $controller->list(...), [], 'GET')->getStatusCode(), 'the list stays readable');
    }
}
