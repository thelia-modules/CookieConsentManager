<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\ExecutableChanges;
use CookieConsentManager\Catalogue\CatalogueEntry;
use CookieConsentManager\Catalogue\CatalogueReader;
use PHPUnit\Framework\TestCase;

final class ExecutableChangesTest extends TestCase
{
    private function changes(): ExecutableChanges
    {
        return new ExecutableChanges(new CatalogueReader(__DIR__.'/../Fixtures/catalogue.json'));
    }

    /** @param array<string, string> $parameters */
    private function draft(array $parameters, string $key = 'matomo'): array
    {
        return ['services' => [['key' => $key, 'purpose' => 'analytics', 'enabled' => true, 'parameters' => $parameters]]];
    }

    public function testAChangedAddressIsExecutableAndAChangedIdentifierIsNot(): void
    {
        $before = $this->draft(['matomoId' => '1', 'matomoHost' => 'https://a.test/']);

        self::assertTrue($this->changes()->between($before, $this->draft(['matomoId' => '1', 'matomoHost' => 'https://b.test/'])));
        self::assertFalse($this->changes()->between($before, $this->draft(['matomoId' => '2', 'matomoHost' => 'https://a.test/'])));
        self::assertFalse($this->changes()->between($before, $before));
    }

    public function testAnAddressSetFromNothingIsExecutableAndAnEmptiedOneIsNot(): void
    {
        self::assertTrue($this->changes()->between($this->draft([]), $this->draft(['matomoHost' => 'https://a.test/'])));
        self::assertFalse($this->changes()->between($this->draft(['matomoHost' => 'https://a.test/']), $this->draft([])));
        self::assertFalse($this->changes()->between($this->draft(['matomoHost' => 'https://a.test/']), $this->draft(['matomoHost' => ''])));
    }

    public function testTheCodeOrTheAddressOfACustomServiceIsExecutable(): void
    {
        $custom = static fn (string $code, string $url): array => ['services' => [['key' => 'chat', 'custom' => true, 'code' => $code, 'script_url' => $url, 'parameters' => []]]];

        self::assertTrue($this->changes()->between($custom('a();', ''), $custom('b();', '')));
        self::assertTrue($this->changes()->between($custom('', 'https://a.test/x.js'), $custom('', 'https://b.test/x.js')));
        self::assertFalse($this->changes()->between($custom('a();', ''), $custom('a();', '')));
    }

    public function testAParameterOfAnUnknownServiceIsExecutable(): void
    {
        self::assertTrue($this->changes()->between($this->draft([], 'gone'), $this->draft(['whatever' => 'x'], 'gone')));
    }

    public function testAParameterWithoutFlagInTheCatalogueIsExecutable(): void
    {
        $entry = CatalogueEntry::fromArray(['key' => 'x', 'purpose' => 'analytics', 'params' => [['name' => 'a'], ['name' => 'b', 'executable' => false]]]);

        self::assertTrue($entry->isExecutable('a'), 'no flag: treated as executable');
        self::assertFalse($entry->isExecutable('b'));
        self::assertTrue($entry->isExecutable('absent'));
    }

    public function testPathsOfTheDraft(): void
    {
        $changes = $this->changes();

        self::assertTrue($changes->isExecutablePath('services.matomo.parameters.matomoHost'));
        self::assertFalse($changes->isExecutablePath('services.matomo.parameters.matomoId'));
        self::assertTrue($changes->isExecutablePath('services.chat.code'));
        self::assertTrue($changes->isExecutablePath('services.chat.script_url'));
        self::assertFalse($changes->isExecutablePath('services.matomo.enabled'));
        self::assertFalse($changes->isExecutablePath('general.layout'));
    }

    public function testTheParametersTheAuditNamedAreFlaggedInTheShippedCatalogue(): void
    {
        $changes = new ExecutableChanges(new CatalogueReader(__DIR__.'/../../../Resources/catalogue/catalogue.json'));

        foreach (['matomotm.matomotmUrl', 'matomo.matomoHost', 'atinternet.atLibUrl', 'dynatrace.dynatraceJSPath', 'posthog.posthogHost', 'gtag.gtagCustomDomain', 'googletagmanager.googletagmanagerId', 'multiplegoogletagmanager.multiplegoogletagmanagerId', 'tagcommander.tagcommanderid'] as $path) {
            self::assertTrue($changes->isExecutablePath('services.'.preg_replace('/\./', '.parameters.', $path, 1)), $path);
        }
        foreach (['gtag.gtagUa', 'clarity.clarity', 'facebookpixel.facebookpixelId'] as $path) {
            self::assertFalse($changes->isExecutablePath('services.'.preg_replace('/\./', '.parameters.', $path, 1)), $path);
        }
    }
}
