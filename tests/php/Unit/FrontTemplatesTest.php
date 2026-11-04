<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Front\FrontTexts;
use CookieConsentManager\Front\TemplateContext;
use CookieConsentManager\Tests\Support\CatalogueTranslator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class FrontTemplatesTest extends TestCase
{
    /** @return iterable<string, array{0: string}> */
    public static function themes(): iterable
    {
        yield 'default' => ['default'];
        yield 'flexy' => ['flexy'];
    }

    private function twig(string $theme): Environment
    {
        return new Environment(new FilesystemLoader(\dirname(__DIR__, 3).'/templates/frontOffice/'.$theme), ['strict_variables' => false, 'autoescape' => 'html']);
    }

    /** @return array<string, mixed> */
    private function variables(): array
    {
        $texts = (new FrontTexts(new CatalogueTranslator()))->forLanguage('fr') + ['title' => 't', 'body' => 'b', 'reject' => 'r', 'accept' => 'a', 'customize' => 'c', 'close' => 'x', 'preferences' => 'p', 'free_text' => ''];
        $service = [
            'key' => 'gtag', 'name' => 'Google Analytics 4', 'purpose' => 'analytics', 'controller' => 'Google Ireland Limited',
            'policy_url' => 'https://policies.google.com/privacy', 'cookies' => ['_ga', 'plain'],
            'cookie_details' => [['name' => '_ga', 'duration' => '13 mois'], ['name' => 'plain', 'duration' => '']],
        ];

        return [
            'ccm_contract' => TemplateContext::CONTRACT, 'layout' => 'bar', 'merchant_name' => '', 'texts' => $texts, 'close_enabled' => false,
            'purposes' => [['code' => 'analytics', 'label' => 'Mesure', 'description' => 'd', 'services' => [$service]]],
            'exempt_services' => [['key' => 'matomo', 'name' => 'Matomo', 'purpose' => 'analytics', 'controller' => 'Acme', 'policy_url' => '', 'cookies' => [], 'cookie_details' => []]],
            'policy_url' => '/policy', 'colors' => [], 'language' => 'fr', 'locale' => 'fr_FR', 'mode' => 'live',
        ];
    }

    #[DataProvider('themes')]
    public function testThePanelAndThePageShowTheDurationOfEachCookie(string $theme): void
    {
        $panel = $this->twig($theme)->render('CookieConsentManager/preferences-panel.html.twig', $this->variables());
        $page = $this->twig($theme)->render('CookieConsentManager/preferences-page.html.twig', $this->variables() + ['head' => '', 'body' => '']);

        foreach ([$panel, $page] as $html) {
            self::assertStringContainsString('Cookies : _ga (13 mois), plain', $html);
        }
    }

    #[DataProvider('themes')]
    public function testTheBannerListsThePurposesBeforeTheBodyAndKeepsLinksOutOfTheScrollingText(string $theme): void
    {
        $variables = $this->variables();
        $variables['close_enabled'] = true;
        $variables['texts']['body'] = 'BODYTEXT';
        $html = $this->twig($theme)->render('CookieConsentManager/banner.html.twig', $variables);

        self::assertLessThan(strpos($html, 'BODYTEXT'), strpos($html, 'ccm-purposes'), 'purposes first');
        $text = substr($html, (int) strpos($html, 'class="ccm-text"'), (int) strpos($html, 'class="ccm-actions"') - (int) strpos($html, 'class="ccm-text"'));
        self::assertStringNotContainsString('href="/policy"', $text, 'the policy link is outside the scrolling text');
        self::assertMatchesRegularExpression('~class="ccm-foot">.*data-ccm-action="close_reject".*href="/policy"~s', $html);
    }

    #[DataProvider('themes')]
    public function testACookieListWithoutDetailsFallsBackToTheNames(string $theme): void
    {
        $variables = $this->variables();
        unset($variables['purposes'][0]['services'][0]['cookie_details']);

        self::assertStringContainsString('Cookies : _ga, plain', $this->twig($theme)->render('CookieConsentManager/preferences-panel.html.twig', $variables));
    }

    #[DataProvider('themes')]
    public function testThePlaceholderNamesTheControllerAndLinksItsPolicy(string $theme): void
    {
        $html = $this->twig($theme)->render('CookieConsentManager/placeholder.html.twig', $this->variables() + [
            'service' => ['key' => 'gtag', 'name' => 'GA4', 'purpose_label' => 'Mesure', 'controller' => 'Google Ireland Limited', 'policy_url' => 'https://policies.google.com/privacy'],
            'attributes' => [], 'src' => 'https://x.test',
        ]);

        self::assertStringContainsString('Responsable : Google Ireland Limited', $html);
        self::assertStringContainsString('href="https://policies.google.com/privacy"', $html);
    }

    #[DataProvider('themes')]
    public function testThePlaceholderOnlyUsesPhrasingContentSoItFitsInAParagraph(string $theme): void
    {
        $html = $this->twig($theme)->render('CookieConsentManager/placeholder.html.twig', $this->variables() + [
            'service' => ['key' => 'gtag', 'name' => 'GA4', 'purpose_label' => 'Mesure', 'controller' => 'X', 'policy_url' => ''],
            'attributes' => [], 'src' => 'https://x.test',
        ]);

        self::assertDoesNotMatchRegularExpression('~<(div|p|ul|section)\b~i', $html);
    }

    #[DataProvider('themes')]
    public function testWithoutJavaScriptThePlaceholderOffersALinkToThePreferencesPage(string $theme): void
    {
        $html = $this->twig($theme)->render('CookieConsentManager/placeholder.html.twig', $this->variables() + [
            'service' => ['key' => 'gtag', 'name' => 'GA4', 'purpose_label' => '', 'controller' => 'X', 'policy_url' => ''],
            'attributes' => [], 'src' => 'https://x.test',
        ]);

        self::assertMatchesRegularExpression('~<a class="[^"]*ccm-nojs-only" href="/ccm/preferences">~', $html);
        self::assertMatchesRegularExpression('~<span class="ccm-actions ccm-js-only">\s*<button[^>]*service_allow~', $html, 'the buttons only show with JavaScript');
    }

    #[DataProvider('themes')]
    public function testTheFirstLevelMentionsTheExemptMeasurementWithTheOppositionLink(string $theme): void
    {
        $banner = $this->twig($theme)->render('CookieConsentManager/banner.html.twig', $this->variables());

        self::assertStringContainsString('(Matomo)', $banner);
        self::assertMatchesRegularExpression('~<button[^>]*data-ccm-open data-ccm-open-exempt>~', $banner);

        $variables = $this->variables();
        $variables['exempt_services'] = [];
        self::assertStringNotContainsString('data-ccm-open-exempt', $this->twig($theme)->render('CookieConsentManager/banner.html.twig', $variables));
    }

    public function testTheControllerFallsBackToTheNameWhileItIsEmpty(): void
    {
        self::assertSame('Acme', TemplateContext::controller(['name' => 'Acme', 'controller' => '']));
        self::assertSame('Acme', TemplateContext::controller(['name' => 'Acme', 'controller' => '  ']));
        self::assertSame('Acme', TemplateContext::controller(['name' => 'Acme']));
        self::assertSame('Acme Ltd', TemplateContext::controller(['name' => 'Acme', 'controller' => 'Acme Ltd']));
    }

    public function testThePolicyUrlOfTheCurrentLanguageWinsOverTheDefaultOne(): void
    {
        $policy = ['url' => '/politique', 'urls' => ['en' => '/en/policy', 'de' => ' ']];

        self::assertSame('/en/policy', TemplateContext::policyUrl($policy, 'en'));
        self::assertSame('/politique', TemplateContext::policyUrl($policy, 'fr'));
        self::assertSame('/politique', TemplateContext::policyUrl($policy, 'de'), 'a blank URL falls back');
        self::assertSame('', TemplateContext::policyUrl([], 'en'));
    }

    public function testThePolicyLabelIsCookiePolicy(): void
    {
        self::assertSame('Politique de cookies', (new FrontTexts(new CatalogueTranslator()))->forLanguage('fr')['policy']);
        self::assertSame('Cookie policy', (new FrontTexts(new CatalogueTranslator()))->forLanguage('en')['policy']);
    }
}
