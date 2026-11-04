<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Preset\PresetApplier;
use CookieConsentManager\Preset\PresetCatalogue;
use CookieConsentManager\Tests\Support\CcmTestCase;
use CookieConsentManager\Transfer\ConfigExporter;
use CookieConsentManager\Transfer\ConfigSchemaValidator;
use CookieConsentManager\Validation\DraftValidator;

final class PresetImportTest extends CcmTestCase
{
    /** @return array<string, mixed> */
    private function importFrance(string $language, bool $main): array
    {
        $france = (new PresetCatalogue(\dirname(__DIR__, 3).'/Resources/presets'))->find('fr-FR');
        $this->ccm->drafts->replace((new PresetApplier())->apply($this->publishableDraft(), $france, $language, $main), 'alice');

        return $this->ccm->drafts->load();
    }

    public function testTheFrancePresetPassesTheValidatorAndLeavesTheShopSpecificValuesAlone(): void
    {
        $before = $this->publishableDraft();
        $draft = $this->importFrance('fr', true);

        $issues = $this->ccm->validator->validate($draft);
        self::assertFalse(DraftValidator::hasErrors($issues), implode(' | ', array_map(static fn ($issue): string => $issue->field.': '.$issue->message, $issues)));
        self::assertSame([], array_values(array_filter($issues, static fn ($issue): bool => \in_array($issue->code, ['implicit_consent', 'label_gap'], true))), 'no consent-by-browsing wording, balanced buttons');

        self::assertSame($before['services'], $draft['services']);
        self::assertSame('/politique-de-confidentialite', $draft['policy']['url']);
        self::assertSame('Tout accepter', $draft['texts']['fr']['accept']);
        self::assertSame(25, $draft['registry']['retention_months']);
        self::assertSame(['fr' => 'fr-FR'], $draft['presets']);
    }

    public function testTheAttachmentSurvivesExportImportAndPublication(): void
    {
        $this->importFrance('fr', true);

        $exported = ConfigExporter::toJson($this->ccm->exporter->export());
        self::assertSame([], (new ConfigSchemaValidator(\dirname(__DIR__, 3).'/Config/schema/ccm-config-1.schema.json'))->violations($exported));
        self::assertSame(['fr' => 'fr-FR'], json_decode($exported, true)['presets']);

        $this->ccm->drafts->replace($this->ccm->normalizer->normalize([]), 'alice');
        self::assertSame([], $this->ccm->drafts->load()['presets']);
        $this->ccm->importer->import($exported, 'alice');
        self::assertSame(['fr' => 'fr-FR'], $this->ccm->drafts->load()['presets'], 'import');

        $this->ccm->drafts->confirmEnvironment();
        $version = $this->ccm->publisher->publish('alice', false, 'preset');
        self::assertSame(['fr' => 'fr-FR'], $this->ccm->versions->snapshot($version)['config']['presets'], 'published version');
    }

    public function testAVersionPublishedBeforePresetsExistedShowsNoPhantomChange(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $this->ccm->publisher->publish('alice', false, 'before');
        $latest = $this->ccm->versions->latest();
        $snapshot = $this->ccm->versions->snapshot($latest);
        unset($snapshot['config']['presets']);

        self::assertSame([], \CookieConsentManager\Publication\ConfigDiff::between($snapshot['config'], $this->ccm->drafts->load()));
    }
}
