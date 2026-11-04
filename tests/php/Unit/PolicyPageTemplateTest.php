<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\PolicyPageTemplate;
use CookieConsentManager\Admin\ShopIdentity;
use CookieConsentManager\Catalogue\JointResponsibility;
use CookieConsentManager\Draft\DraftNormalizer;
use CookieConsentManager\Front\FrontTexts;
use CookieConsentManager\Front\TemplateContext;
use CookieConsentManager\Tests\Support\CatalogueTranslator;
use CookieConsentManager\Tests\Support\ModuleRoutes;
use PHPUnit\Framework\TestCase;

final class PolicyPageTemplateTest extends TestCase
{
    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        $draft = (new DraftNormalizer())->normalize(['general' => ['renewal_months' => 6], 'registry' => ['retention_months' => 25, 'ip_mode' => 'none']]);

        return ['config' => $draft, 'services_resolved' => [
            ['key' => 'gtag', 'name' => 'Google Analytics 4', 'purpose' => 'analytics', 'controller' => 'Google Ireland Limited', 'policy_url' => 'https://policies.google.com/privacy', 'cookies' => ['_ga', '_gid'], 'cookie_details' => [['name' => '_ga', 'duration_days' => 730], ['name' => '_gid']]],
            ['key' => 'x', 'name' => 'Z <b>', 'purpose' => 'ads', 'controller' => '', 'policy_url' => '', 'cookies' => [], 'storage' => [], 'domains' => []],
        ]];
    }

    /** @param array{name: string, address: string, email: string} $identity */
    private function template(array $identity = ['name' => 'Boutique <Test> SAS', 'address' => '1 rue de la Paix, 75002 Paris', 'email' => 'contact@example.org'], string $baseUrl = ''): PolicyPageTemplate
    {
        $frontTexts = new FrontTexts(new CatalogueTranslator());
        $shop = new class($identity) extends ShopIdentity {
            /** @param array{name: string, address: string, email: string} $identity */
            public function __construct(private readonly array $identity)
            {
            }

            public function read(): array
            {
                return $this->identity;
            }
        };

        return new PolicyPageTemplate($frontTexts, new TemplateContext($frontTexts), $shop, new JointResponsibility(__DIR__.'/../../../Resources/catalogue/joint-responsibility.json'), ModuleRoutes::urls($baseUrl));
    }

    public function testItKeepsWhatIsStableAndSendsToTheLivePageForTheServices(): void
    {
        $html = $this->template()->render($this->snapshot(), 'fr');

        self::assertStringContainsString('édité par Boutique &lt;Test&gt; SAS, 1 rue de la Paix, 75002 Paris', $html, 'identity of the shop, escaped');
        self::assertStringContainsString('contact@example.org', $html);
        self::assertStringContainsString('<a href="/ccm/preferences">Liste à jour des services et des cookies</a>', $html);
        self::assertStringContainsString('redemandé après 6 mois', $html);
        self::assertStringContainsString('pendant 25 mois', $html);
        self::assertStringContainsString('ne contient pas votre adresse IP', $html);
        foreach (['Google Ireland Limited', '_ga', 'policies.google.com', 'Mesure d', 'Z &lt;b&gt;', 'chacun est responsable de son propre'] as $frozen) {
            self::assertStringNotContainsString($frozen, $html, 'nothing that changes with the services');
        }
        self::assertStringNotContainsString('&lt;p&gt;', $html, 'no escaped tag shown to the visitor');
        self::assertStringNotContainsString('conjointement', $html, 'no joint service here');
    }

    public function testTheLinkToThePreferencesCarriesTheBasePathOfTheShop(): void
    {
        $html = $this->template(baseUrl: '/shop')->render($this->snapshot(), 'fr');

        self::assertStringContainsString('<a href="/shop/ccm/preferences">Liste à jour des services et des cookies</a>', $html);
    }

    public function testItStatesTheJointResponsibilityWhenAServiceCallsForIt(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['services_resolved'][] = ['key' => 'facebookpixel', 'name' => 'Meta Pixel', 'purpose' => 'ads', 'controller' => 'Meta Platforms Ireland Limited', 'policy_url' => '', 'cookies' => []];

        $html = $this->template()->render($snapshot, 'fr');

        self::assertStringContainsString('responsables conjointement de la collecte des données sur ce site et de leur transmission', $html);
        self::assertStringContainsString('Le fournisseur est seul responsable', $html);
    }

    public function testWhatTheShopDoesNotKnowIsLeftOut(): void
    {
        $html = $this->template(['name' => '', 'address' => '', 'email' => ''])->render($this->snapshot(), 'fr');

        self::assertStringNotContainsString('édité par', $html);
        self::assertStringNotContainsString('Qui édite ce site', $html);
        self::assertStringContainsString('Qui est responsable des traceurs', $html);
    }

    public function testItIsWrittenInTheRequestedLanguage(): void
    {
        $html = $this->template()->render($this->snapshot(), 'de');

        self::assertStringContainsString('Diese Website wird herausgegeben von Boutique &lt;Test&gt; SAS', $html);
        self::assertStringContainsString('Aktuelle Liste der Dienste und Cookies', $html);
    }
}
