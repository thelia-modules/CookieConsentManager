<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Controller\Admin\HelpController;
use CookieConsentManager\Tests\Support\CcmTestCase;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;

/** The help screen serves the guide in the language of the back-office: French for French, English for every other language. */
final class HelpLanguageTest extends CcmTestCase
{
    private function helpPage(string $locale, string $guide): string
    {
        $this->loginWithModuleRights([AccessManager::VIEW]);

        $request = Request::create('http://localhost/admin/module/CookieConsentManager/help', 'GET', ['guide' => $guide]);
        $request->setLocale($locale);
        $request->setSession(static::getContainer()->get('request_stack')->getCurrentRequest()->getSession());
        static::getContainer()->get('request_stack')->push($request);

        try {
            $response = $this->getService(HelpController::class)->show($request);
        } finally {
            static::getContainer()->get('request_stack')->pop();
        }

        self::assertSame(200, $response->getStatusCode());

        return (string) $response->getContent();
    }

    public function testAFrenchBackOfficeGetsTheFrenchGuides(): void
    {
        self::assertStringContainsString('Guide administrateur : gérer le consentement', $this->helpPage('fr_FR', 'administrateur'));
        self::assertStringContainsString('Guide intégrateur', $this->helpPage('fr_FR', 'integrateur'));
    }

    public function testAnyOtherBackOfficeLanguageGetsTheEnglishGuides(): void
    {
        foreach (['en_US', 'de_DE', 'es_ES'] as $locale) {
            $administrator = $this->helpPage($locale, 'administrateur');
            self::assertStringContainsString('Administrator guide', $administrator, $locale);
            self::assertStringNotContainsString('gérer le consentement', $administrator, $locale);
            self::assertStringContainsString('Overriding a template', $this->helpPage($locale, 'integrateur'), $locale);
        }
    }
}
