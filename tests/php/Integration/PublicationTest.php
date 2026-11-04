<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Model\VersionQuery;
use CookieConsentManager\Publication\CanonicalJson;
use CookieConsentManager\Publication\ConfigDiff;
use CookieConsentManager\Publication\PublicationRefusedException;
use CookieConsentManager\Publication\Publisher;
use CookieConsentManager\Tests\Support\CcmTestCase;
use CookieConsentManager\Tests\Support\Wiring;
use CookieConsentManager\Validation\DraftValidator;

final class PublicationTest extends CcmTestCase
{
    public function testPublishesAnImmutableNumberedVersionAndPurgesTheSharedCache(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');

        $first = $this->ccm->publisher->publish('alice', false, 'first');
        $second = $this->ccm->publisher->publish('bob', true);

        self::assertSame($first->getVersion() + 1, $second->getVersion());
        self::assertSame('alice', $first->getAuthor());
        self::assertSame(2, $this->ccm->cache->purges);
        self::assertSame($second->getVersion(), $this->ccm->drafts->baseVersion());

        $snapshot = $this->ccm->versions->snapshot($first);
        self::assertSame('ccm-snapshot/1', $snapshot['schema']);
        self::assertSame(['gtag', 'youtube'], array_column($snapshot['services_resolved'], 'key'));
        self::assertSame(['analytics_storage'], $this->ccm->builder->build($snapshot, 'fr_FR', 1, 'abc', 0)['purposes'][0]['signals']);

        // same configuration, same hashes
        self::assertSame($first->getConfigHash(), $second->getConfigHash());
        self::assertSame($first->getServedHash(), $second->getServedHash());

        // immutable through the model
        $first->setAuthor('mallory');
        $first->save();
        self::assertSame('alice', VersionQuery::create()->findPk($first->getId())->getAuthor());
        $first->delete();
        self::assertNotNull(VersionQuery::create()->findPk($first->getId()));
    }

    public function testAPublicationWithoutChangeIsRefusedUnlessForcedOrSubstantialAndAModuleUpdateIsAChange(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $first = $this->ccm->publisher->publish('alice');

        try {
            $this->ccm->publisher->publish('alice', false, null, true);
            self::fail('nothing changed since the last version');
        } catch (\CookieConsentManager\Publication\NothingToPublishException $refusal) {
            self::assertSame($first->getVersion(), $refusal->version);
        }
        self::assertSame($first->getVersion(), $this->ccm->versions->latest()->getVersion(), 'no version was created');

        $forced = $this->ccm->publisher->publish('alice');
        self::assertSame($first->getVersion() + 1, $forced->getVersion(), 'without the guard (--force) the version is created');
        $substantial = $this->ccm->publisher->publish('alice', true, null, true);
        self::assertSame($forced->getVersion() + 1, $substantial->getVersion(), 'a substantial republication shows the banner again: it is a change');

        // the module was updated since the last version: the served hash differs, the publication goes through
        \Propel\Runtime\Propel::getWriteConnection('TheliaMain')->exec(\sprintf("UPDATE ccm_version SET served_hash = '000000000000', module_version = '0.0.1' WHERE version = %d", $substantial->getVersion()));
        $afterUpdate = $this->ccm->publisher->publish('alice', false, null, true);
        self::assertSame($substantial->getVersion() + 1, $afterUpdate->getVersion());
    }

    public function testTheRealSharedCacheIsPurgedWhenItExistsAndNothingHappensWithout(): void
    {
        if (!static::getContainer()->has('Thelia\Core\SharedCache\SharedCacheInvalidator')) {
            self::markTestSkipped('The shared cache of Thelia exists from 3.3.');
        }
        $invalidator = static::getContainer()->get('Thelia\Core\SharedCache\SharedCacheInvalidator');

        self::assertTrue((new \CookieConsentManager\Publication\SharedCachePurger($invalidator))->purgeAll());
        self::assertFalse((new \CookieConsentManager\Publication\SharedCachePurger())->purgeAll());
    }

    public function testTheServedHashFollowsTheScriptTheModuleVersionAndTheTemplates(): void
    {
        $base = Publisher::servedHash('c', '1.0.0', 's', []);

        self::assertNotSame($base, Publisher::servedHash('c', '1.0.0', 's2', []));
        self::assertNotSame($base, Publisher::servedHash('c', '1.0.1', 's', []));
        self::assertNotSame($base, Publisher::servedHash('c2', '1.0.0', 's', []));
        self::assertNotSame($base, Publisher::servedHash('c', '1.0.0', 's', [['template' => 'banner.html.twig', 'source' => 'theme', 'hash' => 'x']]));
        self::assertSame(12, \strlen($base));
    }

    public function testRefusesAnInvalidDraftAndNamesEveryProblem(): void
    {
        $draft = $this->publishableDraft();
        $draft['services'] = [
            ['key' => 'gtag', 'purpose' => 'analytics', 'enabled' => true, 'parameters' => []],
            ['key' => 'matomo', 'purpose' => 'analytics', 'enabled' => true, 'parameters' => ['matomoId' => 'abc', 'matomoHost' => 'https://x.test']],
            ['key' => 'youtube', 'purpose' => 'essential', 'enabled' => true, 'parameters' => []],
            ['key' => 'obscure', 'purpose' => 'personalization', 'enabled' => true, 'parameters' => []],
            ['key' => 'ghost', 'purpose' => 'analytics', 'enabled' => true, 'parameters' => []],
            ['key' => 'gtag-clone', 'custom' => true, 'kind' => 'script', 'purpose' => 'ads', 'enabled' => true, 'name' => 'x', 'controller' => 'x', 'policy_url' => 'https://x.test', 'script_url' => 'http://insecure.test/a.js'],
            ['key' => 'youtube2', 'custom' => true, 'kind' => 'declarative', 'purpose' => 'essential', 'enabled' => true, 'name' => 'x', 'controller' => 'x', 'policy_url' => 'https://x.test'],
        ];
        $this->ccm->drafts->replace($draft, 'alice');
        $before = VersionQuery::create()->count();

        try {
            $this->ccm->publisher->publish('alice');
            self::fail('The publication must be refused');
        } catch (PublicationRefusedException $exception) {
            $codes = array_map(static fn ($issue): string => $issue->code.'@'.$issue->field, $exception->issues);
        }

        foreach ([
            'param_required@services.gtag.parameters.gtagUa',
            'param_pattern@services.matomo.parameters.matomoId',
            'essential_forbidden@services.youtube.purpose',
            'experimental@services.obscure',
            'unknown_service@services.ghost',
            'script_url@services.gtag-clone.script_url',
            'essential_forbidden@services.youtube2.purpose',
        ] as $expected) {
            self::assertContains($expected, $codes);
        }
        self::assertSame($before, VersionQuery::create()->count(), 'nothing was written');
    }

    public function testACustomKeyCollidingWithTheCatalogueIsRefused(): void
    {
        $draft = $this->publishableDraft();
        $draft['services'][] = ['key' => 'youtube', 'custom' => true, 'kind' => 'declarative', 'purpose' => 'media', 'enabled' => true, 'name' => 'x', 'controller' => 'x', 'policy_url' => 'https://x.test'];

        $codes = array_map(static fn ($issue): string => $issue->code, $this->ccm->validator->validate($this->ccm->normalizer->normalize($draft)));

        self::assertContains('key_collision', $codes);
        self::assertContains('key_duplicate', $codes);
    }

    public function testGoogleTagManagerRefusesActivationWhileItsModuleIsActive(): void
    {
        $draft = $this->publishableDraft();
        $draft['services'] = [['key' => 'googletagmanager', 'purpose' => 'ads', 'enabled' => true, 'parameters' => ['googletagmanagerId' => 'GTM-ABCD12']]];
        $draft = $this->ccm->normalizer->normalize($draft);

        self::assertFalse(DraftValidator::hasErrors($this->ccm->validator->validate($draft)));
        self::assertTrue(DraftValidator::hasErrors((new Wiring(true))->validator->validate($draft)));
    }

    public function testAnExemptionNeedsEveryConditionAndNeverAppliesToGoogleAnalytics(): void
    {
        $draft = $this->publishableDraft();
        $draft['services'][] = ['key' => 'matomo', 'purpose' => 'analytics', 'enabled' => true, 'parameters' => ['matomoId' => '3', 'matomoHost' => 'https://s.test']];
        $draft['exempt_measurement'] = [
            ['service' => 'matomo', 'conditions' => ['audience_measurement_only' => true]],
            ['service' => 'gtag', 'conditions' => array_fill_keys(['audience_measurement_only', 'no_cross_site_tracking', 'no_third_party_sharing', 'limited_retention', 'opposition_offered'], true)],
        ];

        $codes = array_map(static fn ($issue): string => $issue->code.'@'.$issue->field, $this->ccm->validator->validate($this->ccm->normalizer->normalize($draft)));

        self::assertContains('exempt_conditions@exempt_measurement.0', $codes);
        self::assertContains('exempt_google@exempt_measurement.1', $codes);
    }

    public function testPublishedConfigFollowsTheContract(): void
    {
        $draft = $this->publishableDraft();
        $draft['general']['renewal_months'] = 6;
        $draft['services'][] = ['key' => 'chat', 'custom' => true, 'kind' => 'declarative', 'purpose' => 'support', 'enabled' => true, 'name' => 'Chat', 'controller' => 'C', 'policy_url' => 'https://c.test', 'cookies' => [['name' => 'chat_id']]];
        $draft['services'][] = ['key' => 'matomo', 'purpose' => 'analytics', 'enabled' => true, 'parameters' => ['matomoId' => '3', 'matomoHost' => 'https://s.test']];
        $draft['exempt_measurement'] = [['service' => 'matomo', 'conditions' => array_fill_keys(['audience_measurement_only', 'no_cross_site_tracking', 'no_third_party_sharing', 'limited_retention', 'opposition_offered'], true)]];
        $this->ccm->drafts->replace($draft, 'alice');

        $first = $this->ccm->publisher->publish('alice', true);
        $second = $this->ccm->publisher->publish('alice', false);
        $config = $this->ccm->builder->build($this->ccm->versions->snapshot($second), 'fr_FR', $second->getVersion(), $second->getServedHash(), $this->ccm->versions->substantialVersionUpTo($second->getVersion()));

        self::assertSame(1, $config['format']);
        self::assertSame($first->getVersion(), $config['substantialVersion']);
        self::assertSame('/ccm/consent', $config['endpoint']);
        self::assertSame(\sprintf('/ccm/%d-%s.js', $second->getVersion(), $second->getServedHash()), $config['script']);
        self::assertSame(182, $config['repromptDays']);
        self::assertSame('basic', $config['gcm']['mode']);
        self::assertCount(1, $config['gcm']['defaults']);
        self::assertSame([], $config['gcm']['defaults'][0]['region']);
        self::assertSame('granted', $config['gcm']['defaults'][0]['security_storage']);
        self::assertSame('denied', $config['gcm']['defaults'][0]['analytics_storage']);
        self::assertSame(0, $config['gcm']['waitForUpdate']);
        // every displayed purpose, signal-less ones included
        self::assertSame(['analytics', 'media', 'support'], array_column($config['purposes'], 'code'));
        self::assertEquals(['key' => 'gtag', 'purpose' => 'analytics', 'kind' => 'script', 'googleTag' => true, 'user' => (object) ['gtagUa' => 'G-ABC123XYZ'], 'cookies' => ['_ga', '_ga_*'], 'storage' => []], array_column($config['services'], null, 'key')['gtag']);
        self::assertSame('Chat', array_column($config['services'], null, 'key')['chat']['name']);
        self::assertEquals([['key' => 'matomo', 'user' => (object) ['matomoId' => '3', 'matomoHost' => 'https://s.test'], 'cookies' => ['_pk_id.*', '_pk_ses.*']]], $config['exempt'], 'the exempted service carries its parameters, or its fragment cannot run');
        self::assertNotContains('matomo', array_column($config['services'], 'key'));
        self::assertEquals($config, $this->ccm->builder->build($this->ccm->versions->snapshot($second), 'fr_FR', $second->getVersion(), $second->getServedHash(), $first->getVersion()), 'deterministic');
    }

    public function testAdvancedModeKeepsRegionsAndTheGlobalFallbackLast(): void
    {
        $draft = $this->publishableDraft();
        $draft['consent_mode'] = ['mode' => 'advanced', 'wait_for_update_ms' => 700, 'regions' => [['iso' => ['FR', 'DE'], 'default' => ['analytics_storage' => 'denied']], ['iso' => ['US-CA'], 'default' => ['ad_storage' => 'granted']]]];
        $this->ccm->drafts->replace($draft, 'alice');
        $version = $this->ccm->publisher->publish('alice');

        $config = $this->ccm->builder->build($this->ccm->versions->snapshot($version), 'en_US', 1, 'h', 0);

        self::assertSame(700, $config['gcm']['waitForUpdate']);
        self::assertSame([['FR', 'DE'], ['US-CA'], []], array_column($config['gcm']['defaults'], 'region'));
        self::assertSame('granted', $config['gcm']['defaults'][1]['ad_storage']);
    }

    public function testRestoringAVersionGivesADraftWithoutDifference(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $version = $this->ccm->publisher->publish('alice');

        $edited = $this->ccm->drafts->load();
        $edited['general']['layout'] = 'modal';
        $edited['services'] = [];
        $this->ccm->drafts->replace($edited, 'alice');
        self::assertNotSame([], ConfigDiff::between($this->ccm->drafts->load(), $this->ccm->versions->snapshot($version)['config']));

        $this->ccm->restorer->restore($version->getVersion(), 'alice');

        self::assertSame([], ConfigDiff::between($this->ccm->drafts->load(), $this->ccm->versions->snapshot($version)['config']));
        self::assertSame($version->getVersion(), $this->ccm->drafts->baseVersion());
    }

    public function testASnapshotKeepsTheCatalogueDataItWasPublishedWith(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $version = $this->ccm->publisher->publish('alice');

        $snapshot = $this->ccm->versions->snapshot($version);

        self::assertSame(['_ga', '_ga_*'], $snapshot['services_resolved'][0]['cookies']);
        self::assertSame(CanonicalJson::hash(['config' => $snapshot['config'], 'services' => $snapshot['services_resolved']]), $version->getConfigHash());
    }
}
