<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\VerificationAnalyzer;
use PHPUnit\Framework\TestCase;

final class VerificationAnalyzerTest extends TestCase
{
    private const SNAPSHOT = [
        'config' => ['consent_mode' => ['mode' => 'basic']],
        'services_resolved' => [
            ['key' => 'gtag', 'purpose' => 'analytics', 'google_tag' => true, 'domains' => ['www.googletagmanager.com', '*.google-analytics.com'], 'cookies' => ['_ga', '_ga_*']],
            ['key' => 'payzen', 'purpose' => 'essential', 'domains' => ['static.payzen.eu'], 'cookies' => []],
        ],
    ];

    public function testAThirdPartyOfAServiceBeforeConsentIsKoAndUnknownIsToHandle(): void
    {
        $result = (new VerificationAnalyzer())->analyze([
            ['phase' => 'before', 'url' => 'https://shop.test/', 'hosts' => ['shop.test', 'cdn.shop.test', 'www.googletagmanager.com', 'region1.google-analytics.com', 'static.payzen.eu', 'tracker.unknown.io'], 'cookies' => ['PHPSESSID', 'ccm_consent', '_ga', '_ga_ABC123', 'mystery']],
        ], self::SNAPSHOT, 'shop.test', 5);

        $domains = array_column($result['domains'], 'verdict', 'host');
        self::assertEqualsCanonicalizing(['www.googletagmanager.com' => 'ko', 'region1.google-analytics.com' => 'ko', 'static.payzen.eu' => 'ok', 'tracker.unknown.io' => 'todo'], $domains, 'own hosts are ignored, essential services are allowed');
        $cookies = array_column($result['cookies'], 'verdict', 'name');
        self::assertSame(['_ga' => 'ko', '_ga_ABC123' => 'ko', 'mystery' => 'todo'], $cookies, 'whitelisted cookies are ignored');
    }

    public function testAdvancedModeExpectsGoogleBeforeChoiceButNotAfterReject(): void
    {
        $snapshot = self::SNAPSHOT;
        $snapshot['config']['consent_mode']['mode'] = 'advanced';
        $result = (new VerificationAnalyzer())->analyze([
            ['phase' => 'before', 'url' => '/', 'hosts' => ['www.googletagmanager.com']],
            ['phase' => 'after_reject', 'url' => '/', 'hosts' => ['www.googletagmanager.com']],
        ], $snapshot, 'shop.test');

        self::assertSame('ko', $result['domains'][0]['verdict'], 'after Reject all it is ko, whatever the mode');
        self::assertSame(['warn', 'ko'], array_column($result['domains'][0]['seen'], 'verdict'));
    }

    public function testIframesCountAsDomainsAndDoubleGtagAndVersionMismatchAreFlagged(): void
    {
        $result = (new VerificationAnalyzer())->analyze([
            ['phase' => 'before', 'url' => '/p', 'hosts' => [], 'iframes' => ['https://static.payzen.eu/x'], 'google_tags' => ['gtag_js' => ['G-1'], 'gtm_js' => ['GTM-1']], 'version' => 4],
        ], self::SNAPSHOT, 'shop.test', 5);

        self::assertSame('static.payzen.eu', $result['domains'][0]['host']);
        self::assertSame(['gtag_twice', 'version_mismatch'], array_column($result['alerts'], 'code'));
    }

    public function testADomainNoServiceOwnsIsSaidToBeLoadedOutsideTheModuleAndFontsAreTheThemeOnes(): void
    {
        $result = (new VerificationAnalyzer())->analyze([
            ['phase' => 'before', 'url' => '/', 'hosts' => ['fonts.gstatic.com', 'tracker.unknown.io', 'static.payzen.eu']],
        ], self::SNAPSHOT, 'shop.test', 5);

        $byHost = array_column($result['domains'], null, 'host');
        self::assertSame('fonts', $byHost['fonts.gstatic.com']['origin']);
        self::assertSame('outside', $byHost['tracker.unknown.io']['origin']);
        self::assertSame('managed', $byHost['static.payzen.eu']['origin']);
        self::assertSame(['managed' => 1, 'outside' => 1, 'fonts' => 1], $result['origins']);
    }

    public function testAGoogleTagContainerHardcodedInThePageIsNotBlamedOnTheGtagServiceAndDoublesIt(): void
    {
        $result = (new VerificationAnalyzer())->analyze([
            ['phase' => 'before', 'url' => '/', 'hosts' => ['www.googletagmanager.com'], 'google_tags' => ['gtag_js' => [], 'gtm_js' => ['GTM-ABC123']]],
        ], self::SNAPSHOT, 'shop.test', 5);

        self::assertSame('outside', $result['domains'][0]['origin']);
        self::assertSame('google_tag_hardcoded', $result['domains'][0]['reason']);
        self::assertSame('todo', $result['domains'][0]['verdict']);
        self::assertSame(['gtag_theme_and_module'], array_column($result['alerts'], 'code'));
    }

    public function testTheHostOfAnExemptServiceIsAManagedDomainAndItsCookiesAreAllowed(): void
    {
        $snapshot = self::SNAPSHOT;
        $snapshot['config']['exempt_measurement'] = [['service' => 'matomo']];
        $snapshot['services_resolved'][] = ['key' => 'matomo', 'purpose' => 'analytics', 'domains' => [], 'user' => ['matomoHost' => 'https://stats.example.com/', 'matomoId' => '3'], 'cookies' => ['_pk_*']];

        $result = (new VerificationAnalyzer())->analyze([
            ['phase' => 'before', 'url' => '/', 'hosts' => ['stats.example.com', 'tracker.unknown.io'], 'cookies' => ['_pk_id.3.abc']],
        ], $snapshot, 'shop.test', 5);

        $byHost = array_column($result['domains'], null, 'host');
        self::assertSame('managed', $byHost['stats.example.com']['origin']);
        self::assertSame('matomo', $byHost['stats.example.com']['service']);
        self::assertSame('ok', $byHost['stats.example.com']['verdict']);
        self::assertSame('outside', $byHost['tracker.unknown.io']['origin']);
        self::assertSame('ok', $result['cookies'][0]['verdict']);
        self::assertSame(['stats.example.com'], VerificationAnalyzer::serviceDomains($snapshot['services_resolved'][2], true));
    }

    public function testTheHostOfAServiceThatIsNeitherExemptNorEssentialStaysUnknown(): void
    {
        $snapshot = self::SNAPSHOT;
        $snapshot['services_resolved'][] = ['key' => 'matomo', 'purpose' => 'analytics', 'domains' => [], 'user' => ['matomoHost' => 'https://stats.example.com/'], 'cookies' => []];

        $result = (new VerificationAnalyzer())->analyze([['phase' => 'before', 'url' => '/', 'hosts' => ['stats.example.com']]], $snapshot, 'shop.test', 5);

        self::assertSame('outside', $result['domains'][0]['origin']);
    }

    public function testADomainIsOneLineWithAPageCountAndAFewExamplesPerPhase(): void
    {
        $reports = [];
        foreach (['/a', '/b', '/c', '/d'] as $path) {
            $reports[] = ['phase' => 'before', 'url' => $path.'?ccm_audit=secret', 'hosts' => ['static.payzen.eu']];
        }
        $reports[] = ['phase' => 'after_reject', 'url' => '/a', 'hosts' => ['static.payzen.eu']];

        $result = (new VerificationAnalyzer())->analyze($reports, self::SNAPSHOT, 'shop.test', 5);

        self::assertCount(1, $result['domains']);
        self::assertSame(4, $result['domains'][0]['pages']['before']['count']);
        self::assertSame(['/a', '/b', '/c'], $result['domains'][0]['pages']['before']['examples']);
        self::assertSame(1, $result['domains'][0]['pages']['after_reject']['count']);
    }

    public function testTheAuditTokenNeverAppearsInTheResult(): void
    {
        $result = (new VerificationAnalyzer())->analyze([
            ['phase' => 'before', 'url' => 'https://shop.test/?a=1&ccm_audit=SECRETTOKEN&ccm_audit_phase=before', 'hosts' => ['tracker.unknown.io']],
            ['phase' => 'after_reject', 'url' => 'https://shop.test/?ccm_audit=SECRETTOKEN&ccm_audit_phase=after_reject', 'hosts' => ['tracker.unknown.io']],
        ], self::SNAPSHOT, 'shop.test', 5);

        self::assertStringNotContainsString('SECRETTOKEN', json_encode($result, \JSON_THROW_ON_ERROR));
        self::assertSame('https://shop.test/?a=1', $result['domains'][0]['seen'][0]['url']);
        self::assertSame('https://shop.test/', $result['domains'][0]['seen'][1]['url']);
    }
}
