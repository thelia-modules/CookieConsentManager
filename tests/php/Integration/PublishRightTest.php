<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Controller\Admin\DashboardController;
use CookieConsentManager\Model\VersionQuery;
use CookieConsentManager\Tests\Support\CcmTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Tools\TokenProvider;

/** The right a write asks for comes from the server-side step, never from a field the browser sends. */
final class PublishRightTest extends CcmTestCase
{
    /** @param list<string> $accesses */
    private function wizardPost(array $accesses, array $fields): Response
    {
        $this->loginWithModuleRights($accesses);

        $request = Request::create('http://localhost/admin/module/CookieConsentManager/wizard', 'POST', $fields);
        $request->setSession(static::getContainer()->get('request_stack')->getCurrentRequest()->getSession());
        $request->request->set('_token', $this->getService(TokenProvider::class)->assignToken());
        static::getContainer()->get('request_stack')->push($request);

        try {
            return $this->getService(DashboardController::class)->saveWizard($request);
        } finally {
            static::getContainer()->get('request_stack')->pop();
        }
    }

    public function testStepFourNeedsThePublishRightWhateverFieldsAreSent(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'test');
        $before = VersionQuery::create()->count();

        // no step_action at all: this used to need only UPDATE
        $response = $this->wizardPost([AccessManager::VIEW, AccessManager::UPDATE], ['step' => '4']);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame($before, VersionQuery::create()->count(), 'nothing was published');
    }

    public function testStepFourPublishesWithThePublishRight(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'test');
        $before = VersionQuery::create()->count();

        $response = $this->wizardPost([AccessManager::VIEW, AccessManager::UPDATE, AccessManager::CREATE], ['step' => '4']);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($before + 1, VersionQuery::create()->count());
    }

    public function testEarlierStepsOnlyNeedUpdate(): void
    {
        $response = $this->wizardPost([AccessManager::VIEW, AccessManager::UPDATE], ['step' => '1', 'keys' => []]);

        self::assertNotSame(403, $response->getStatusCode(), strip_tags(substr((string) $response->getContent(), 0, 3000)));
    }
}
