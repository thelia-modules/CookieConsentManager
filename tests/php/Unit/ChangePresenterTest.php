<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\ChangePresenter;
use CookieConsentManager\Admin\FieldLabeler;
use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Tests\Support\CatalogueTranslator;
use PHPUnit\Framework\TestCase;

final class ChangePresenterTest extends TestCase
{
    private function present(array $before, array $after): array
    {
        $translator = new CatalogueTranslator('fr_FR');
        $presenter = new ChangePresenter(new FieldLabeler(new CatalogueReader(__DIR__.'/../Fixtures/catalogue.json'), $translator), $translator);

        return array_column($presenter->present($before, $after, 'fr'), null, 'path');
    }

    public function testAValueTypedByTheAdministratorIsNeverTranslated(): void
    {
        $rows = $this->present(
            ['texts' => ['en' => ['title' => 'Yes', 'body' => 'x']], 'general' => ['close_button' => true]],
            ['texts' => ['en' => ['title' => 'No', 'body' => 'granted']], 'general' => ['close_button' => false]],
        );

        self::assertSame('Yes', $rows['texts.en.title']['before']);
        self::assertSame('No', $rows['texts.en.title']['after'], 'typed "No" stays "No"');
        self::assertSame('granted', $rows['texts.en.body']['after']);
        self::assertSame('Oui', $rows['general.close_button']['before'], 'a boolean is still written in the language of the interface');
        self::assertSame('Non', $rows['general.close_button']['after']);
    }

    public function testTheCodesOfTheModuleAreTranslated(): void
    {
        $rows = $this->present(
            ['consent_mode' => ['regions' => [['iso' => ['FR'], 'default' => ['analytics_storage' => 'granted']]]], 'registry' => ['ip_mode' => 'truncated']],
            ['consent_mode' => ['regions' => [['iso' => ['FR'], 'default' => ['analytics_storage' => 'denied']]]], 'registry' => ['ip_mode' => 'none']],
        );

        self::assertSame('Accordé', $rows['consent_mode.regions.0.default.analytics_storage']['before']);
        self::assertSame('Refusé', $rows['consent_mode.regions.0.default.analytics_storage']['after']);
        self::assertSame('aucune', $rows['registry.ip_mode']['after']);
    }

    public function testTheCodeOfAnAddedCustomServiceIsShownInFull(): void
    {
        $service = ['key' => 'chat', 'custom' => true, 'kind' => 'custom', 'purpose' => 'ads', 'enabled' => true, 'name' => 'Chat', 'script_url' => 'https://chat.test/a.js', 'code' => "window.chat = 1;\nload();"];
        $rows = $this->present(['services' => []], ['services' => [$service]]);

        self::assertSame("window.chat = 1;\nload();", $rows['services.chat.code']['after']);
        self::assertSame('https://chat.test/a.js', $rows['services.chat.script_url']['after']);
        self::assertArrayHasKey('services.chat', $rows, 'the folded row stays');
    }

    public function testTheCodeOfAChangedCustomServiceIsShownInFull(): void
    {
        $service = ['key' => 'chat', 'custom' => true, 'kind' => 'custom', 'purpose' => 'ads', 'enabled' => true, 'name' => 'Chat', 'code' => 'a();'];
        $rows = $this->present(['services' => [$service]], ['services' => [['code' => 'b();'] + $service]]);

        self::assertSame('a();', $rows['services.chat.code']['before']);
        self::assertSame('b();', $rows['services.chat.code']['after']);
    }
}
