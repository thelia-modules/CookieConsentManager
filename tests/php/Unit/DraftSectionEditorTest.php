<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\DraftSectionEditor;
use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Draft\DraftNormalizer;
use PHPUnit\Framework\TestCase;

final class DraftSectionEditorTest extends TestCase
{
    private DraftSectionEditor $editor;

    /** @var array<string, mixed> */
    private array $draft;

    protected function setUp(): void
    {
        $this->editor = new DraftSectionEditor(new CatalogueReader(__DIR__.'/../Fixtures/catalogue.json'));
        $this->draft = (new DraftNormalizer())->normalize([]);
    }

    public function testGeneralFlagsAreOffWhenTheirBoxIsNotPosted(): void
    {
        $draft = $this->editor->general($this->draft, ['general' => ['enabled' => '1', 'renewal_months' => '9'], 'policy' => ['url' => ' /privacy ']]);

        self::assertTrue($draft['general']['enabled'], 'the legacy "banner enabled" field is ignored: omitting it changes nothing');
        $off = $this->draft;
        $off['general']['enabled'] = false;
        self::assertFalse($this->editor->general($off, ['general' => ['enabled' => '1']])['general']['enabled'], 'and posting it changes nothing either');
        self::assertFalse($draft['general']['close_button']);
        self::assertFalse($draft['general']['reload_on_script_withdraw'], 'an unchecked box turns the flag off');
        self::assertSame(9, $draft['general']['renewal_months']);
        self::assertSame('/privacy', $draft['policy']['url']);
    }

    public function testCatalogueServiceKeepsOnlyTheParametersTheCatalogueDeclares(): void
    {
        $draft = $this->editor->catalogueService($this->draft, 'gtag', ['enabled' => '1', 'parameters' => ['gtagUa' => ' G-ABCD1234 ', 'injected' => 'x']]);

        self::assertSame([['key' => 'gtag', 'purpose' => 'analytics', 'enabled' => true, 'parameters' => ['gtagUa' => 'G-ABCD1234']]], $draft['services']);
        self::assertSame($draft, $this->editor->catalogueService($draft, 'not-in-catalogue', ['enabled' => '1']));
    }

    public function testCustomServiceKeyIsDerivedAndCookiesParsedLineByLine(): void
    {
        $draft = $this->editor->customService($this->draft, ['name' => 'Chat Maison', 'controller' => 'Exemple SAS', 'policy_url' => 'https://example.com/p', 'purpose' => 'support', 'kind' => 'declarative', 'cookies' => "chat_id; .example.com; 365; session\n\nother"]);

        $service = $draft['services'][0];
        self::assertSame('custom_chat_maison', $service['key']);
        self::assertSame([['name' => 'chat_id', 'domain' => '.example.com', 'duration_days' => 365, 'description' => 'session'], ['name' => 'other']], $service['cookies']);
    }

    public function testRegionsAreKeptWhenTheFormDoesNotCarryThem(): void
    {
        $draft = $this->editor->consentMode($this->draft, ['regions' => [['iso' => 'fr, es-ct', 'signals' => ['analytics_storage' => 'denied', 'ad_storage' => '']]]]);
        self::assertSame([['iso' => ['FR', 'ES-CT'], 'default' => ['analytics_storage' => 'denied']]], $draft['consent_mode']['regions']);

        $again = $this->editor->consentMode($draft, ['consent_mode' => ['mode' => 'basic']]);
        self::assertSame($draft['consent_mode']['regions'], $again['consent_mode']['regions']);
        self::assertSame('basic', $again['consent_mode']['mode']);
    }

    public function testEmptyLanguageIsNotStoredAndResetRestoresTheDefault(): void
    {
        $draft = $this->editor->texts($this->draft, ['texts' => ['es' => ['title' => '', 'accept' => ''], 'fr' => ['title' => 'Mon titre']]]);
        self::assertArrayNotHasKey('es', $draft['texts']);
        self::assertSame('Mon titre', $draft['texts']['fr']['title']);

        $reset = $this->editor->resetTexts($draft, 'fr');
        self::assertArrayNotHasKey('fr', $reset['texts'], 'a reset language falls back to the default text of that language');
    }

    public function testANewPurposeCannotBeEssentialOrDuplicate(): void
    {
        $input = ['purposes' => ['analytics' => ['order' => '1']]];
        self::assertCount(1, $this->editor->purposes($this->draft, $input + ['new_purpose' => ['code' => 'essential']])['purposes']);
        self::assertCount(1, $this->editor->purposes($this->draft, $input + ['new_purpose' => ['code' => 'analytics']])['purposes']);
        self::assertSame('newsletter', $this->editor->purposes($this->draft, $input + ['new_purpose' => ['code' => 'Newsletter']])['purposes'][1]['code']);
    }
}
