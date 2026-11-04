<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Publication\ConfigDiff;
use CookieConsentManager\Publication\PublicationRefusedException;
use CookieConsentManager\Tests\Support\CcmTestCase;
use CookieConsentManager\Transfer\ConfigExporter;
use CookieConsentManager\Transfer\ImportRefusedException;

final class ConfigTransferTest extends CcmTestCase
{
    public function testExportImportRoundTripLeavesNoDifferenceOnACompleteConfig(): void
    {
        $document = json_decode($this->completeConfigJson(), true);

        $report = $this->ccm->importer->import($this->completeConfigJson(), 'alice');
        $imported = $this->ccm->drafts->load();

        // nothing of the file was lost: the draft equals the normalized content of the file
        $expected = $this->ccm->normalizer->normalize(array_diff_key($document, array_flip(['schema', 'exported_at', 'module_version', 'source_version', 'environment'])));
        self::assertSame([], ConfigDiff::between($expected, $imported));
        self::assertCount(10, $imported['services']);
        self::assertSame(['de', 'en', 'fr'], array_keys($imported['texts']));
        self::assertSame('avis', $this->service($imported, 'avis')['key']);
        self::assertSame('window.px=1;', $this->service($imported, 'pixel_maison')['code']);

        // export, import again, compare
        $exported = ConfigExporter::toJson($this->ccm->exporter->export());
        self::assertSame([], $this->ccm->importer->import($exported, 'alice')->skippedServices);
        self::assertSame([], ConfigDiff::between($imported, $this->ccm->drafts->load()), 'export, import, empty draft diff');

        // the exported document validates against the schema it ships with
        self::assertSame([], (new \CookieConsentManager\Transfer\ConfigSchemaValidator(\dirname(__DIR__, 3).'/Config/schema/ccm-config-1.schema.json'))->violations($exported));

        self::assertSame(['policy.url', 'consent_mode.regions', 'services.googletagmanager.parameters.googletagmanagerId', 'services.gtag.parameters.gtagUa', 'services.matomo.parameters.matomoHost', 'services.matomo.parameters.matomoId'], $report->environmentPending);
        self::assertTrue($report->policyResolved);
    }

    public function testAnImportedValueStaysPendingUntilItIsTickedOrReplacedByANewValue(): void
    {
        $this->ccm->importer->import($this->completeConfigJson(), 'alice');
        $fields = new \CookieConsentManager\Transfer\EnvironmentFields();
        $draft = $this->ccm->drafts->load();

        $listed = array_column($fields->pending($draft), 'value', 'path');
        self::assertSame('G-ABC123XYZ', $listed['services.gtag.parameters.gtagUa'], 'the value of the source site is shown');
        self::assertCount(6, $listed);

        // nothing ticked, nothing typed: nothing is confirmed
        $none = $fields->resolve($draft, [], ['services.gtag.parameters.gtagUa' => '   ']);
        self::assertSame([], $none['resolved']);
        self::assertCount(6, $none['draft']['_meta']['environment_pending']);

        // one field ticked, another replaced by a typed value
        $some = $fields->resolve($draft, ['policy.url'], ['services.gtag.parameters.gtagUa' => 'G-NEWSITE99']);
        self::assertSame(['policy.url', 'services.gtag.parameters.gtagUa'], $some['resolved']);
        self::assertCount(4, $some['draft']['_meta']['environment_pending']);
        $gtag = array_values(array_filter($some['draft']['services'], static fn (array $service): bool => 'gtag' === $service['key']))[0];
        self::assertSame('G-NEWSITE99', $gtag['parameters']['gtagUa']);
        $this->ccm->drafts->replace($some['draft'], 'alice');

        try {
            $this->ccm->publisher->publish('alice');
            self::fail('the four fields left pending still refuse the publication');
        } catch (PublicationRefusedException $refusal) {
            self::assertCount(4, array_filter($refusal->issues, static fn ($issue): bool => 'environment_unconfirmed' === $issue->code));
        }
    }

    public function testTheEnvironmentFieldsMustBeConfirmedBeforePublishing(): void
    {
        $this->ccm->importer->import($this->completeConfigJson(), 'alice');

        try {
            $this->ccm->publisher->publish('alice');
            self::fail('Publication must wait for the confirmation');
        } catch (PublicationRefusedException $exception) {
            self::assertStringContainsString('policy.url', $exception->getMessage());
        }

        $this->ccm->drafts->confirmEnvironment();

        $published = $this->ccm->publisher->publish('alice');

        self::assertSame($published->getVersion(), $this->ccm->versions->latest()->getVersion());
        self::assertSame([], $this->ccm->drafts->load()['_meta'] ?? []);
    }

    public function testAnImportCreatesADraftAndNeverAPublication(): void
    {
        $before = $this->ccm->versions->latest()?->getVersion();

        $this->ccm->importer->import($this->completeConfigJson(), 'alice');

        self::assertSame($before, $this->ccm->versions->latest()?->getVersion());
        self::assertSame(0, $this->ccm->cache->purges);
    }

    public function testRefusesAnUnknownSchemaAnUnknownKeyAndBrokenJson(): void
    {
        $json = $this->completeConfigJson();

        $cases = [
            'unknown schema' => [str_replace('"ccm-config/1"', '"ccm-config/2"', $json), 'schema'],
            'unknown key' => [str_replace('"layout": "modal",', '"layout": "modal", "sneaky": true,', $json), 'sneaky'],
            'bad enum' => [str_replace('"layout": "modal"', '"layout": "popup"', $json), 'layout'],
            'bad color' => [str_replace('"#0b5fff"', '"red"', $json), 'primary'],
        ];

        foreach ($cases as $name => [$candidate, $needle]) {
            self::assertNotSame($json, $candidate, $name);
            try {
                $this->ccm->importer->import($candidate, 'alice');
                self::fail($name.' must be refused');
            } catch (ImportRefusedException $exception) {
                self::assertStringContainsString($needle, $exception->getMessage(), $name);
            }
        }

        $this->expectException(ImportRefusedException::class);
        $this->ccm->importer->import('{broken', 'alice');
    }

    public function testExperimentalServicesAreIgnoredWhileTheSettingIsOff(): void
    {
        $report = $this->ccm->importer->import(str_replace('"experimental_services": true', '"experimental_services": false', $this->completeConfigJson()), 'alice');

        self::assertSame(['obscure'], $report->skippedServices);
        self::assertNull($this->service($this->ccm->drafts->load(), 'obscure'));
    }

    public function testAPolicyUrlThatMatchesNoPageIsReported(): void
    {
        $json = str_replace('"/politique-de-confidentialite"', '"/page-inconnue"', $this->completeConfigJson());

        self::assertFalse($this->ccm->importer->import($json, 'alice')->policyResolved);
    }

    /** @param array<string, mixed> $draft */
    private function service(array $draft, string $key): ?array
    {
        return array_column($draft['services'], null, 'key')[$key] ?? null;
    }
}
