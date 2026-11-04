<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Admin\ContrastChecker;
use CookieConsentManager\Admin\DraftSectionEditor;
use CookieConsentManager\Admin\DraftWriter;
use CookieConsentManager\Publication\PublicationRefusedException;
use CookieConsentManager\Tests\Support\CcmTestCase;

/** What a preset makes mandatory (R4: #1 responsible of every active service, #3 a cookie policy page of its own). */
final class PresetRulesTest extends CcmTestCase
{
    /** @return list<string> */
    private function codes(array $draft): array
    {
        return array_map(static fn ($issue): string => $issue->code, $this->ccm->validator->validate($this->ccm->normalizer->normalize($draft)));
    }

    public function testAnActiveServiceWithoutResponsibleIsRefusedUnderAPresetOnly(): void
    {
        $draft = $this->publishableDraft();
        $draft['services'][] = ['key' => 'matomo', 'purpose' => 'analytics', 'enabled' => true, 'parameters' => ['matomoId' => '1', 'matomoHost' => 'https://stats.example.com']];

        self::assertNotContains('controller_missing_preset', $this->codes($draft), 'no preset: only an alert');

        $draft['presets'] = ['fr' => 'fr-FR'];
        $draft['policy']['urls']['fr'] = '/politique-de-cookies';
        self::assertContains('controller_missing_preset', $this->codes($draft));

        $draft['services'][2]['controller'] = 'Mon entreprise SAS';
        self::assertNotContains('controller_missing_preset', $this->codes($draft), 'typed by the administrator');
    }

    public function testTheFacebookPixelNamesItsLegalEntityByDefault(): void
    {
        $controllers = new \CookieConsentManager\Draft\ServiceControllers(\dirname(__DIR__, 3).'/Resources/catalogue/controllers.json');

        self::assertSame('Meta Platforms Ireland Limited', $controllers->default('facebookpixel'));
    }

    public function testTheFrenchPresetRefusesThePageOfTermsAndConditionsAsCookiePolicy(): void
    {
        $draft = $this->publishableDraft();
        $draft['presets'] = ['fr' => 'fr-FR'];
        $draft['policy']['url'] = '/terms-and-conditions.html';

        self::assertContains('policy_url_terms', $this->codes($draft));

        $draft['policy']['urls'] = ['fr' => '/politique-de-cookies', 'en' => '/cookie-policy'];
        self::assertNotContains('policy_url_terms', $this->codes($draft), 'an address of its own for every active language');
    }

    public function testEveryActiveLanguageNeedsADedicatedPolicyUnderThePresetOfTheMainLanguage(): void
    {
        $draft = $this->publishableDraft();
        $draft['presets'] = ['fr' => 'fr-FR'];
        $draft['policy']['url'] = '/terms-and-conditions.html';
        $draft['policy']['urls'] = ['fr' => '/politique-de-cookies'];

        $issues = array_filter($this->ccm->validator->validate($this->ccm->normalizer->normalize($draft)), static fn ($issue): bool => 'policy_url_terms' === $issue->code);
        self::assertSame(['policy.urls.en'], array_values(array_map(static fn ($issue): string => $issue->field, $issues)), 'English falls back to the terms of sale');

        $draft['policy']['urls']['en'] = '/cookie-policy';
        self::assertNotContains('policy_url_terms', $this->codes($draft));

        $draft['policy']['url'] = '/politique-de-confidentialite';
        unset($draft['policy']['urls']['en']);
        self::assertNotContains('policy_url_terms', $this->codes($draft), 'a fallback to a dedicated address is allowed');
        self::assertNotContains('policy_url_preset_missing', $this->codes($draft));
    }

    public function testALanguageWithoutPresetAsksNothingWhenTheMainLanguageHasNone(): void
    {
        $draft = $this->publishableDraft();
        $draft['presets'] = ['en' => 'fr-FR'];
        $draft['policy']['url'] = '/terms-and-conditions.html';
        $draft['policy']['urls'] = ['en' => '/cookie-policy'];

        self::assertNotContains('policy_url_terms', $this->codes($draft), 'only the language attached to the preset is checked');
    }

    public function testTheFrenchPresetRefusesAMissingAddressAndThePublicationFails(): void
    {
        $draft = $this->publishableDraft();
        $draft['presets'] = ['fr' => 'fr-FR'];
        $draft['policy']['url'] = null;
        $this->ccm->drafts->replace($this->ccm->normalizer->normalize($draft), 'tester');

        self::assertContains('policy_url_preset_missing', $this->codes($draft));
        $this->expectException(PublicationRefusedException::class);
        $this->ccm->publisher->publish('tester');
    }

    public function testAnotherPresetOrNoPresetAsksNothingMore(): void
    {
        $draft = $this->publishableDraft();
        $draft['policy']['url'] = '/terms-and-conditions.html';

        self::assertNotContains('policy_url_terms', $this->codes($draft));
        $draft['presets'] = ['de' => 'de-DE'];
        self::assertNotContains('policy_url_terms', $this->codes($draft));
    }

    public function testTheLifetimesAndTheControllerTypedForACatalogueServiceSurviveTheDatabase(): void
    {
        $editor = new DraftSectionEditor($this->ccm->catalogue);
        $writer = new DraftWriter($this->ccm->drafts, $this->ccm->normalizer, $this->ccm->validator, new ContrastChecker());
        $candidate = $editor->catalogueService($this->ccm->drafts->load(), 'gtag', ['enabled' => '1', 'controller' => 'Ma société', 'parameters' => ['gtagUa' => 'G-ABC123DEF4'], 'cookie_durations' => ['_ga' => '100', '_gcl_au' => '', 'unknown' => '5', '_ga_*' => 'x']]);

        self::assertTrue($writer->save($candidate, ['services.gtag'], 'tester')->saved);

        $service = $this->ccm->drafts->load()['services'][0];
        self::assertSame('Ma société', $service['controller']);
        self::assertSame(['_ga' => 100], $service['cookie_durations'], 'only declared cookies with a number are kept');
    }

    public function testAnImplausibleLifetimeIsRefused(): void
    {
        $draft = $this->publishableDraft();
        $draft['services'][0]['cookie_durations'] = ['_ga' => 99999];

        self::assertContains('cookie_duration', $this->codes($draft));
    }

    public function testAServiceWithoutKnownLifetimeBlocksThePublicationUnderThePresetOfTheMainLanguage(): void
    {
        $draft = $this->publishableDraft();
        $draft['services'][] = ['key' => 'matomo', 'purpose' => 'analytics', 'enabled' => true, 'controller' => 'Ma société', 'parameters' => ['matomoId' => '1', 'matomoHost' => 'stats.example.org']];
        $draft['policy']['urls'] = ['fr' => '/politique-de-cookies', 'en' => '/cookie-policy'];

        self::assertNotContains('cookie_duration_missing', $this->codes($draft), 'no preset');
        $draft['presets'] = ['en' => 'fr-FR'];
        self::assertNotContains('cookie_duration_missing', $this->codes($draft), 'a preset on another language than the main one');

        $draft['presets'] = ['fr' => 'fr-FR'];
        $issues = array_values(array_filter($this->ccm->validator->validate($this->ccm->normalizer->normalize($draft)), static fn ($issue): bool => 'cookie_duration_missing' === $issue->code));
        self::assertCount(1, $issues);
        self::assertStringContainsString('Matomo', $issues[0]->message, 'names the service');
        self::assertStringContainsString('_pk_id.*, _pk_ses.*', $issues[0]->message, 'names the cookies');

        $draft['services'][2]['cookie_durations'] = ['_pk_id.*' => 395];
        $issues = array_values(array_filter($this->ccm->validator->validate($this->ccm->normalizer->normalize($draft)), static fn ($issue): bool => 'cookie_duration_missing' === $issue->code));
        self::assertCount(1, $issues, 'a service half dated still blocks');
        self::assertStringContainsString('_pk_ses.*', $issues[0]->message);
        self::assertStringNotContainsString('_pk_id.*', $issues[0]->message, 'only the cookies without a lifetime');
        $draft['presets'] = [];
        self::assertNotContains('cookie_duration_missing', $this->codes($draft), 'no preset: no error');
        $draft['presets'] = ['fr' => 'fr-FR'];

        $draft['services'][2]['cookie_durations'] = ['_pk_id.*' => 395, '_pk_ses.*' => 1];
        self::assertNotContains('cookie_duration_missing', $this->codes($draft), 'every cookie typed by the administrator');

        $draft['services'][2]['cookie_durations'] = [];
        $draft['services'][2]['enabled'] = false;
        self::assertNotContains('cookie_duration_missing', $this->codes($draft), 'a disabled service is not concerned');
    }

    public function testYoutubeUnderTheFrenchPresetNamesOnlyTheCookieWithoutAnOfficialLifetime(): void
    {
        $draft = $this->publishableDraft();
        $draft['policy']['urls'] = ['fr' => '/politique-de-cookies', 'en' => '/cookie-policy'];
        $youtube = 1;
        unset($draft['services'][$youtube]['cookie_durations']);

        $draft['presets'] = ['fr' => 'fr-FR'];
        $issues = array_values(array_filter($this->ccm->validator->validate($this->ccm->normalizer->normalize($draft)), static fn ($issue): bool => 'cookie_duration_missing' === $issue->code));
        self::assertCount(1, $issues);
        self::assertStringContainsString('VISITOR_PRIVACY_METADATA', $issues[0]->message);
        foreach (['VISITOR_INFO1_LIVE', 'YSC', 'PREF', '__Secure-YNID', '__Secure-ROLLOUT_TOKEN'] as $documented) {
            self::assertStringNotContainsString($documented, $issues[0]->message, $documented.' has a documented lifetime');
        }

        $draft['services'][$youtube]['cookie_durations'] = ['VISITOR_PRIVACY_METADATA' => 180];
        self::assertNotContains('cookie_duration_missing', $this->codes($draft), 'lifetime typed by the administrator');
    }

    public function testThePublicationIsRefusedForAServiceWithoutLifetime(): void
    {
        $draft = $this->publishableDraft();
        $draft['services'][] = ['key' => 'matomo', 'purpose' => 'analytics', 'enabled' => true, 'controller' => 'Ma société', 'parameters' => ['matomoId' => '1', 'matomoHost' => 'stats.example.org']];
        $draft['presets'] = ['fr' => 'fr-FR'];
        $draft['policy']['urls'] = ['fr' => '/politique-de-cookies', 'en' => '/cookie-policy'];
        $this->ccm->drafts->replace($this->ccm->normalizer->normalize($draft), 'tester');

        $this->expectException(PublicationRefusedException::class);
        $this->ccm->publisher->publish('tester');
    }

    public function testUniversalAnalyticsIsRefusedWithAClearMessage(): void
    {
        $draft = $this->publishableDraft();
        $draft['services'][] = ['key' => 'analytics', 'purpose' => 'analytics', 'enabled' => true, 'parameters' => []];
        $issues = array_values(array_filter($this->ccm->validator->validate($this->ccm->normalizer->normalize($draft)), static fn ($issue): bool => 'service_discontinued' === $issue->code));

        self::assertCount(1, $issues);
        self::assertStringContainsString('Google Analytics 4', $issues[0]->message);
        self::assertNotContains('unknown_service', $this->codes($draft));
    }
}
