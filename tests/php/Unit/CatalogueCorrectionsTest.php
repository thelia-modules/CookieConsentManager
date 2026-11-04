<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Catalogue\CookieDurations;
use CookieConsentManager\Catalogue\JointResponsibility;
use PHPUnit\Framework\TestCase;

/** Corrections of the real catalogue (REPORT.md): they must survive a new import, which these checks read back. */
final class CatalogueCorrectionsTest extends TestCase
{
    private const ROOT = __DIR__.'/../../../Resources/catalogue';

    public function testGoogleAnalytics4DoesNotAnnounceTheCookiesOfUniversalAnalytics(): void
    {
        $entry = (new CatalogueReader(self::ROOT.'/catalogue.json'))->find('gtag');

        self::assertNotNull($entry);
        self::assertSame([], array_values(array_filter($entry->cookies, static fn (string $cookie): bool => str_starts_with($cookie, '__utm'))));
        self::assertContains('_ga_*', $entry->cookies);
    }

    public function testTheGoogleServicesListOnlyTheCookiesTheyDocumentAndSet(): void
    {
        $catalogue = new CatalogueReader(self::ROOT.'/catalogue.json');

        self::assertSame(['_ga', '_ga_*'], $catalogue->find('gtag')?->cookies, 'GA4 never sets the advertising cookie of the conversion linker');
        foreach (['googleads', 'googletagmanager'] as $key) {
            self::assertSame(['_ga', '_ga_*', '_gcl_au'], $catalogue->find($key)?->cookies, $key);
        }
    }

    public function testYoutubeListsTheCookiesAndTheStorageKeysMeasuredOnThePlayer(): void
    {
        $youtube = (new CatalogueReader(self::ROOT.'/catalogue.json'))->find('youtube');

        self::assertNotNull($youtube);
        self::assertSame(['VISITOR_INFO1_LIVE', 'YSC', 'PREF', '__Secure-YNID', '__Secure-ROLLOUT_TOKEN', 'VISITOR_PRIVACY_METADATA'], $youtube->cookies);
        self::assertSame(['ytidb::LAST_RESULT_ENTRY_KEY', 'yt-player-caption-persistence', 'yt-icons-last-purged'], $youtube->storage);
    }

    public function testEveryCookieOfTheGoogleServicesHasADocumentedLifetime(): void
    {
        $catalogue = new CatalogueReader(self::ROOT.'/catalogue.json');
        $durations = new CookieDurations(self::ROOT.'/cookie-durations.json');

        foreach (['gtag', 'googleads', 'googletagmanager'] as $key) {
            self::assertSame([], $durations->withoutLifetime([$this->active($key)], $catalogue), $key);
        }
        self::assertSame(182, $durations->all()['youtube']['VISITOR_INFO1_LIVE']['duration_days']);
        self::assertSame(182, $durations->all()['youtube']['__Secure-YNID']['duration_days']);
        self::assertSame(182, $durations->all()['youtube']['__Secure-ROLLOUT_TOKEN']['duration_days']);
    }

    public function testYoutubeNamesOnlyTheCookieWithoutAnOfficialLifetimeUntilTheAdministratorTypesOne(): void
    {
        $catalogue = new CatalogueReader(self::ROOT.'/catalogue.json');
        $durations = new CookieDurations(self::ROOT.'/cookie-durations.json');

        self::assertArrayNotHasKey('VISITOR_PRIVACY_METADATA', $durations->all()['youtube'], 'no official source: no duration in the catalogue');
        self::assertSame(['youtube' => ['VISITOR_PRIVACY_METADATA']], $durations->withoutLifetime([$this->active('youtube')], $catalogue));
        self::assertSame([], $durations->withoutLifetime([$this->active('youtube') + ['cookie_durations' => ['VISITOR_PRIVACY_METADATA' => 180]]], $catalogue));
    }

    public function testUniversalAnalyticsIsExcludedWithItsReason(): void
    {
        $catalogue = new CatalogueReader(self::ROOT.'/catalogue.json');

        self::assertSame('universal-analytics', $catalogue->find('analytics')?->excluded);
        self::assertArrayNotHasKey('analytics', $catalogue->exposed(true));
        self::assertFileDoesNotExist(self::ROOT.'/fragments/analytics.js');
    }

    public function testTheJointResponsibilityIsCarriedByService(): void
    {
        $joint = new JointResponsibility(self::ROOT.'/joint-responsibility.json');

        self::assertTrue($joint->applies('facebookpixel'));
        self::assertTrue($joint->applies('facebook'));
        self::assertFalse($joint->applies('gtag'));
        self::assertFalse($joint->applies('youtube'));
        self::assertTrue($joint->among([['key' => 'gtag'], ['key' => 'facebookpixel']]));
        self::assertFalse($joint->among([['key' => 'gtag'], ['key' => 'facebookpixel', 'custom' => true]]), 'a declared service of the same key is not the catalogue one');
    }

    /** @return array<string, mixed> */
    private function active(string $key): array
    {
        return ['key' => $key, 'enabled' => true, 'purpose' => 'analytics', 'parameters' => []];
    }
}
