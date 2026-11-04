<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Support;

use CookieConsentManager\CookieConsentManager;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Admin;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\ProfileModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

abstract class CcmTestCase extends IntegrationTestCase
{
    protected Wiring $ccm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ccm = new Wiring();
        $this->ccm->drafts->ensure();
    }

    /** @return array<string, mixed> a draft with GA4 and YouTube on, ready to publish */
    protected function publishableDraft(): array
    {
        $draft = $this->ccm->drafts->load();
        $draft['policy']['url'] = '/politique-de-confidentialite';
        $draft['services'] = [
            ['key' => 'gtag', 'purpose' => 'analytics', 'enabled' => true, 'parameters' => ['gtagUa' => 'G-ABC123XYZ']],
            // Google documents no lifetime for VISITOR_PRIVACY_METADATA: the editor types the measured one (180 days)
            ['key' => 'youtube', 'purpose' => 'media', 'enabled' => true, 'parameters' => [], 'cookie_durations' => ['VISITOR_PRIVACY_METADATA' => 180]],
        ];

        return $draft;
    }

    protected function completeConfigJson(): string
    {
        return (string) file_get_contents(__DIR__.'/../Fixtures/complete-config.json');
    }

    /**
     * Logs in an administrator whose profile holds exactly these accesses on the module (rolled back with the test).
     *
     * @param list<string> $accesses AccessManager constants
     */
    protected function loginWithModuleRights(array $accesses): Admin
    {
        $module = ModuleQuery::create()->findOneByCode(CookieConsentManager::getModuleCode());
        if (null === $module) {
            $module = new Module();
            $module->setCode(CookieConsentManager::getModuleCode())->setType(1)->setActivate(1)->setVersion('1.0.0')->setFullNamespace(CookieConsentManager::class)->save();
        }

        $factory = new FixtureFactory($this->getPropelConnection());
        $profile = $factory->profile();
        $rights = new AccessManager(0);
        $rights->build($accesses);
        (new ProfileModule())->setProfileId($profile->getId())->setModuleId($module->getId())->setAccess($rights->getAccessValue())->save();

        $admin = $factory->admin(['profile' => $profile]);
        $this->getService(SecurityContext::class)->setAdminUser($admin);

        return $admin;
    }
}
