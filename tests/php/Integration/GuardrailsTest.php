<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Admin\DraftSectionEditor;
use CookieConsentManager\Publication\ConfigDiff;
use CookieConsentManager\Registry\RegistryOptions;
use CookieConsentManager\Tests\Support\CcmTestCase;
use CookieConsentManager\Validation\ExemptionWhitelist;

/** Lot H2: what the back-office refuses to publish, and what it only flags. */
final class GuardrailsTest extends CcmTestCase
{
    /** @return list<string> */
    private function codes(array $draft, string $severity = 'error'): array
    {
        $draft = $this->ccm->normalizer->normalize($draft);

        return array_values(array_map(
            static fn ($issue): string => $issue->code,
            array_filter($this->ccm->validator->validate($draft), static fn ($issue): bool => $severity === $issue->severity),
        ));
    }

    /** @param list<array{iso: list<string>, default: array<string, string>}> $regions */
    private function withRegions(array $regions): array
    {
        $draft = $this->publishableDraft();
        $draft['consent_mode']['mode'] = 'advanced';
        $draft['consent_mode']['regions'] = $regions;

        return $draft;
    }

    public function testAGrantedDefaultForEuropeIsRefused(): void
    {
        self::assertContains('default_granted', $this->codes($this->withRegions([['iso' => ['FR', 'DE'], 'default' => ['analytics_storage' => 'granted']]])));
        self::assertContains('default_granted', $this->codes($this->withRegions([['iso' => [], 'default' => ['ad_storage' => 'granted']]])), 'a global default');
        self::assertContains('default_granted', $this->codes($this->withRegions([['iso' => ['GB-ENG'], 'default' => ['ad_user_data' => 'granted']]])), 'a subdivision of the United Kingdom');
    }

    public function testAGrantedDefaultOutsideEuropeAndADeniedOneAreAccepted(): void
    {
        self::assertNotContains('default_granted', $this->codes($this->withRegions([['iso' => ['US-CA'], 'default' => ['analytics_storage' => 'granted']]])));
        self::assertNotContains('default_granted', $this->codes($this->withRegions([['iso' => ['FR'], 'default' => ['analytics_storage' => 'denied']]])));
    }

    public function testOnlyTheWhitelistCanBeExempted(): void
    {
        $draft = $this->publishableDraft();
        $draft['services'][] = ['key' => 'evalservice', 'purpose' => 'analytics', 'enabled' => true, 'parameters' => []];
        $conditions = array_fill_keys(\CookieConsentManager\Draft\DefaultSettings::EXEMPT_CONDITIONS, true);

        $draft['exempt_measurement'] = [['service' => 'evalservice', 'conditions' => $conditions]];
        self::assertContains('exempt_not_allowed', $this->codes($draft));

        $draft['services'][] = ['key' => 'matomo', 'purpose' => 'analytics', 'enabled' => true, 'parameters' => ['matomoHost' => 'stats.example.org', 'matomoId' => '1']];
        $draft['exempt_measurement'] = [['service' => 'matomo', 'conditions' => $conditions]];
        self::assertNotContains('exempt_not_allowed', $this->codes($draft));
        self::assertSame(['matomo', 'wysistathightrack', 'plausible', 'simpleanalytics'], array_keys(ExemptionWhitelist::SERVICES));
        foreach (['hotjar', 'clarity', 'contentsquare', 'matomocloud', 'matomohightrack', 'matomotm', 'matomoheatmap', 'piwikpro', 'xiti', 'atinternet', 'pianoanalytics', 'gtag', 'metrica'] as $excluded) {
            self::assertFalse(ExemptionWhitelist::allows($excluded), $excluded);
        }
    }

    public function testACustomServiceIsNeverExemptable(): void
    {
        $draft = $this->publishableDraft();
        $draft['services'][] = ['key' => 'custom_stats', 'custom' => true, 'kind' => 'declarative', 'purpose' => 'analytics', 'enabled' => true, 'name' => 'Stats', 'controller' => 'Me', 'policy_url' => '/p', 'cookies' => []];
        $draft['exempt_measurement'] = [['service' => 'custom_stats', 'conditions' => array_fill_keys(\CookieConsentManager\Draft\DefaultSettings::EXEMPT_CONDITIONS, true)]];

        self::assertContains('exempt_not_allowed', $this->codes($draft));
    }

    public function testACatalogueServiceCannotLeaveItsPurpose(): void
    {
        $draft = $this->publishableDraft();
        $draft['services'][1]['purpose'] = 'personalization';
        self::assertContains('purpose_locked', $this->codes($draft));

        $editor = new DraftSectionEditor($this->ccm->catalogue);
        $saved = $editor->catalogueService($this->publishableDraft(), 'youtube', ['enabled' => '1', 'purpose' => 'ads']);
        self::assertSame('media', array_column($saved['services'], 'purpose', 'key')['youtube'], 'the form cannot move it');
    }

    public function testTheRetentionIsCappedAtSixtyMonths(): void
    {
        $draft = $this->publishableDraft();
        $draft['registry']['retention_months'] = 61;
        self::assertContains('retention_max', $this->codes($draft));
        $draft['registry']['retention_months'] = 60;
        self::assertNotContains('retention_max', $this->codes($draft));
    }

    public function testTextsAreBoundedAndTheButtonsCannotBeEmpty(): void
    {
        $draft = $this->publishableDraft();
        $draft['texts']['en']['title'] = str_repeat('x', 3000);
        $draft['texts']['en']['accept'] = '';
        $codes = $this->codes($draft);

        self::assertContains('too_long', $codes);
        self::assertContains('text_empty', $codes);
    }

    public function testThePolicyAddressMustBeAPathOrHttps(): void
    {
        foreach (['http://example.org/p', 'politique cookies', '//evil.example/p'] as $url) {
            $draft = $this->publishableDraft();
            $draft['policy']['url'] = $url;
            self::assertContains('policy_url_invalid', $this->codes($draft), $url);
        }
        foreach (['/politique-de-cookies', 'https://example.org/cookies'] as $url) {
            $draft = $this->publishableDraft();
            $draft['policy']['url'] = $url;
            self::assertNotContains('policy_url_invalid', $this->codes($draft), $url);
        }
        $draft = $this->publishableDraft();
        $draft['policy']['urls'] = ['fr' => 'http://example.org/p'];
        self::assertContains('policy_url_invalid', $this->codes($draft), 'the address of a language');
    }

    public function testImplicitConsentAndUnequalLabelsAreFlaggedWithoutBlocking(): void
    {
        $draft = $this->publishableDraft();
        // A complete French row: the default draft only carries English texts.
        $draft['texts']['fr'] = array_replace($draft['texts']['en'], ['body' => 'En poursuivant votre navigation, vous acceptez les cookies.']);
        $draft['texts']['en']['body'] = 'By continuing to browse, you agree.';
        $draft['texts']['en']['reject'] = 'No';
        $draft['texts']['en']['accept'] = 'I accept and enjoy an optimal experience';

        $warnings = $this->codes($draft, 'warning');
        sort($warnings);
        self::assertSame(['implicit_consent', 'implicit_consent', 'label_gap'], $warnings);
        self::assertSame([], $this->codes($draft), 'none of them blocks the publication');
    }

    public function testAnOrdinaryTextRaisesNoWarning(): void
    {
        self::assertSame([], $this->codes($this->publishableDraft(), 'warning'));
    }

    public function testTheControllerComesFromTheCatalogueTableUntilTheAdministratorTypesOne(): void
    {
        $snapshot = $this->ccm->assembler->assemble($this->ccm->normalizer->normalize($this->publishableDraft()));
        $controllers = array_column($snapshot['services_resolved'], 'controller', 'key');
        self::assertSame('Google Ireland Limited', $controllers['gtag']);

        $draft = $this->publishableDraft();
        $draft['services'][0]['controller'] = 'Ma Société SAS';
        $snapshot = $this->ccm->assembler->assemble($this->ccm->normalizer->normalize($draft));
        self::assertSame('Ma Société SAS', array_column($snapshot['services_resolved'], 'controller', 'key')['gtag']);

        $draft['services'][0]['key'] = 'obscure';
        $draft['services'][0]['controller'] = '';
        $snapshot = $this->ccm->assembler->assemble($this->ccm->normalizer->normalize($draft));
        self::assertSame(array_column($snapshot['services_resolved'], 'name', 'key')['obscure'], array_column($snapshot['services_resolved'], 'controller', 'key')['obscure'], 'no entity known: the product name, as before');
    }

    public function testThePublishedRetentionRulesThePurgeNotTheDraft(): void
    {
        $draft = $this->publishableDraft();
        $draft['registry']['retention_months'] = 24;
        $this->ccm->drafts->replace($draft, 'tester');
        $this->ccm->publisher->publish('tester', false, null);

        $draft['registry']['retention_months'] = 60;
        $this->ccm->drafts->replace($draft, 'tester');

        self::assertSame(24, (new RegistryOptions($this->ccm->drafts, $this->ccm->versions))->current()['retention_months']);
    }

    public function testTheChangesCarryTheValuesOnBothSides(): void
    {
        $before = $this->ccm->normalizer->normalize($this->publishableDraft());
        $after = $before;
        $after['general']['layout'] = 'modal';

        self::assertSame(['general.layout' => ['before' => 'bar', 'after' => 'modal']], ConfigDiff::changes($before, $after));
    }

    public function testAnEmptyListAgainstAMissingKeyIsNotAChange(): void
    {
        $before = $this->ccm->normalizer->normalize($this->publishableDraft());
        $old = $before;
        unset($old['policy']['urls']);

        self::assertSame([], ConfigDiff::between($old, $before));
    }
}
