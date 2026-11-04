<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Catalogue\CookieDurations;
use CookieConsentManager\Publication\ModuleVersion;
use CookieConsentManager\Publication\SnapshotAssembler;
use CookieConsentManager\Tests\Support\NoOverriddenTemplates;
use PHPUnit\Framework\TestCase;

final class CookieDurationsTest extends TestCase
{
    private const ROOT = __DIR__.'/../../..';

    public function testTheDurationsOfAServiceAreAttachedToTheCookiesOfItsCatalogueEntry(): void
    {
        $durations = new CookieDurations(__DIR__.'/../Fixtures/cookie-durations.json');

        self::assertSame(
            [['name' => '_ga', 'duration_days' => 390], ['name' => '_ga_*'], ['name' => '_gcl_au', 'duration_days' => 90]],
            $durations->details('gtag', ['_ga', '_ga_*', '_gcl_au']),
        );
        self::assertSame([], $durations->details('youtube', ['YSC']), 'no known duration: the plain list of names is kept');
        self::assertSame([], $durations->details('gtag', ['other']), 'a duration of another cookie never leaks');
    }

    public function testAPatternOfTheCatalogueIsReplacedByTheDocumentedCookiesItCovers(): void
    {
        $durations = new CookieDurations(self::ROOT.'/Resources/catalogue/cookie-durations.json');

        self::assertSame(
            [['name' => '_pk_id', 'duration_days' => 396], ['name' => '_pk_ses', 'duration_minutes' => 30], ['name' => '_pk_ref', 'duration_days' => 182], ['name' => '_pk_cvar', 'duration_minutes' => 30], ['name' => 'piwik_ignore']],
            $durations->details('matomo', ['_pk_*', 'piwik_ignore']),
        );
    }

    public function testADurationTypedByTheAdministratorReplacesTheDocumentedOneAndCountsAsKnown(): void
    {
        $durations = new CookieDurations(__DIR__.'/../Fixtures/cookie-durations.json');

        self::assertSame([['name' => '_ga', 'duration_days' => 100], ['name' => '_ga_*'], ['name' => '_gcl_au', 'duration_days' => 90]], $durations->details('gtag', ['_ga', '_ga_*', '_gcl_au'], ['_ga' => 100]));
        self::assertSame([['name' => 'YSC', 'duration_days' => 0]], $durations->details('youtube', ['YSC'], ['YSC' => 0]));
    }

    public function testAnActiveServiceWithAnyCookieOfUnknownLifetimeIsReportedWithThoseCookiesOnly(): void
    {
        $catalogue = new CatalogueReader(self::ROOT.'/Resources/catalogue/catalogue.json');
        $durations = new CookieDurations(self::ROOT.'/Resources/catalogue/cookie-durations.json');
        $services = [
            ['key' => 'facebookpixel', 'enabled' => true, 'purpose' => 'ads', 'parameters' => []],
            ['key' => 'gtag', 'enabled' => true, 'purpose' => 'analytics', 'parameters' => []],
            ['key' => 'youtube', 'enabled' => true, 'purpose' => 'media', 'parameters' => []],
            ['key' => 'matomo', 'enabled' => true, 'purpose' => 'analytics', 'parameters' => []],
            ['key' => 'hotjar', 'enabled' => false, 'purpose' => 'analytics', 'parameters' => []],
            ['key' => 'crisp', 'enabled' => true, 'purpose' => 'support', 'parameters' => []],
            ['key' => 'googlemaps', 'enabled' => true, 'purpose' => 'media', 'parameters' => []],
            ['key' => 'own', 'custom' => true, 'enabled' => true, 'purpose' => 'ads', 'cookies' => [['name' => 'a', 'duration_days' => 10], ['name' => 'b'], ['name' => 'c', 'duration_minutes' => 30]]],
            ['key' => 'full', 'custom' => true, 'enabled' => true, 'purpose' => 'ads', 'cookies' => [['name' => 'a', 'duration_days' => 0]]],
        ];

        $missing = $durations->withoutLifetime($services, $catalogue);

        self::assertSame(['piwik_ignore'], $missing['matomo'], 'half dated: only the cookie without a lifetime');
        self::assertSame(['b'], $missing['own'], 'a declared service half dated');
        self::assertArrayNotHasKey('gtag', $missing);
        self::assertSame(['VISITOR_PRIVACY_METADATA'], $missing['youtube'], 'documented cookies are dated, the one Google does not document is not');
        self::assertArrayNotHasKey('full', $missing, 'a session cookie (0) is a known lifetime');
        self::assertArrayNotHasKey('googlemaps', $missing, 'declares no cookie');
        self::assertArrayNotHasKey('hotjar', $missing, 'disabled');
        self::assertNotSame([], $missing['facebookpixel'], 'no lifetime at all');
        self::assertSame(['hjClosedSurveyInvites'], $durations->withoutLifetime([['key' => 'hotjar', 'enabled' => true, 'purpose' => 'analytics', 'parameters' => [], 'cookie_durations' => ['_hj*' => 365]]], $catalogue)['hotjar'], 'a duration typed for a pattern leaves the other cookie');
    }

    public function testThePublishedSnapshotCarriesTheDurationsOfACatalogueService(): void
    {
        $assembler = new SnapshotAssembler(
            new CatalogueReader(__DIR__.'/../Fixtures/catalogue.json'),
            new ModuleVersion(self::ROOT.'/Config/module.xml'),
            new NoOverriddenTemplates(),
            null,
            new CookieDurations(__DIR__.'/../Fixtures/cookie-durations.json'),
        );
        $draft = (new \CookieConsentManager\Draft\DraftNormalizer())->normalize(['services' => [
            ['key' => 'gtag', 'purpose' => 'analytics', 'enabled' => true, 'parameters' => ['gtagUa' => 'G-ABC123XYZ']],
            ['key' => 'youtube', 'purpose' => 'media', 'enabled' => true, 'parameters' => []],
        ]]);

        $resolved = array_column($assembler->assemble($draft)['services_resolved'], null, 'key');

        self::assertSame([['name' => '_ga', 'duration_days' => 390], ['name' => '_ga_*']], $resolved['gtag']['cookie_details']);
        self::assertSame(['_ga', '_ga_*'], $resolved['gtag']['cookies'], 'the list of names is unchanged');
        self::assertArrayNotHasKey('cookie_details', $resolved['youtube']);
    }

    public function testEveryShippedDurationIsOfACookieTheCatalogueAlreadyDeclares(): void
    {
        $catalogue = new CatalogueReader(self::ROOT.'/Resources/catalogue/catalogue.json');
        $shipped = (new CookieDurations(self::ROOT.'/Resources/catalogue/cookie-durations.json'))->all();

        self::assertJson((string) file_get_contents(self::ROOT.'/Resources/catalogue/cookie-durations.json'));
        foreach ($shipped as $key => $cookies) {
            self::assertNotNull($catalogue->find($key), $key);
            foreach ($cookies as $name => $lifetime) {
                $declared = array_filter($catalogue->find($key)->cookies, static fn (string $cookie): bool => $cookie === $name || CookieDurations::covers($cookie, $name));
                self::assertNotSame([], $declared, $key.' '.$name.': neither declared by the catalogue nor covered by one of its patterns');
                self::assertGreaterThanOrEqual(0, $lifetime['duration_days'] ?? $lifetime['duration_minutes']);
            }
        }
    }
}
