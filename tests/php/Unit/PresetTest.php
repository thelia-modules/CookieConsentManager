<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Draft\DraftNormalizer;
use CookieConsentManager\Preset\Preset;
use CookieConsentManager\Preset\PresetApplier;
use CookieConsentManager\Preset\PresetCatalogue;
use CookieConsentManager\Transfer\ConfigSchemaValidator;
use PHPUnit\Framework\TestCase;

final class PresetTest extends TestCase
{
    private const ROOT = __DIR__.'/../../..';

    private function france(): Preset
    {
        return (new PresetCatalogue(self::ROOT.'/Resources/presets'))->find('fr-FR') ?? throw new \LogicException('fr-FR is not shipped');
    }

    /** @param array<string, mixed> $changes */
    private function other(array $changes = []): Preset
    {
        return Preset::fromDocument(array_replace_recursive([
            'schema' => 'ccm-config/1',
            'preset' => ['code' => 'de-DE', 'label' => 'Deutschland'],
            'general' => ['renewal_months' => 12, 'layout' => 'modal'],
            'texts' => ['de' => ['title' => 'Ihre Wahl', 'accept' => 'Alle akzeptieren']],
        ], $changes));
    }

    public function testEveryShippedPresetIsAValidPartialDocumentOfTheSchema(): void
    {
        $presets = (new PresetCatalogue(self::ROOT.'/Resources/presets'))->all();
        self::assertArrayHasKey('fr-FR', $presets);

        $validator = new ConfigSchemaValidator(self::ROOT.'/Config/schema/ccm-config-1.schema.json');
        foreach ($presets as $preset) {
            self::assertSame([], $validator->violations(json_encode($preset->document, \JSON_THROW_ON_ERROR)), $preset->code);
            self::assertNotSame([], $preset->legalBasis);
        }
    }

    public function testAPresetCannotCarryWhatDependsOnTheShop(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->other(['services' => [['key' => 'gtag']]]);
    }

    public function testAPresetCannotCarryAPolicyAddress(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->other(['policy' => ['url' => '/privacy']]);
    }

    public function testTheMainLanguageReceivesTheTextsTheSharedSettingsAndTheAttachment(): void
    {
        $draft = (new DraftNormalizer())->normalize([]);
        $candidate = (new DraftNormalizer())->normalize((new PresetApplier())->apply($draft, $this->france(), 'fr', true));

        self::assertSame('Tout refuser', $candidate['texts']['fr']['reject']);
        self::assertSame('Mesure d\'audience', $candidate['purposes'][0]['label']['fr']);
        self::assertSame(['fr' => 'fr-FR'], $candidate['presets']);
        self::assertTrue($candidate['general']['close_button']);
        self::assertSame(25, $candidate['registry']['retention_months']);
        self::assertTrue($candidate['consent_mode']['ads_data_redaction']);
        self::assertSame('Politique de cookies', $candidate['policy']['page_title']);

        // what the preset does not carry is intact
        foreach (['appearance', 'services', 'exempt_measurement'] as $untouched) {
            self::assertSame($draft[$untouched], $candidate[$untouched], $untouched);
        }
        self::assertNull($candidate['policy']['url']);
        self::assertTrue($candidate['general']['enabled']);
        self::assertTrue($candidate['general']['block_embedded_iframes']);
        self::assertSame($draft['texts']['en'], $candidate['texts']['en']);
        self::assertSame($draft['purposes'][0]['label']['en'], $candidate['purposes'][0]['label']['en']);
    }

    public function testAnotherLanguageLeavesTheSharedSettingsToThePresetOfTheMainLanguage(): void
    {
        $draft = (new DraftNormalizer())->normalize([]);
        $candidate = (new DraftNormalizer())->normalize((new PresetApplier())->apply($draft, $this->france(), 'de', false));

        self::assertSame($draft['general'], $candidate['general']);
        self::assertSame($draft['consent_mode'], $candidate['consent_mode']);
        self::assertSame($draft['registry'], $candidate['registry']);
        self::assertSame($draft['policy'], $candidate['policy']);
        self::assertSame('Tout accepter', $candidate['texts']['de']['accept'], 'the French text is copied into the chosen language');
        self::assertSame(['de' => 'fr-FR'], $candidate['presets']);
    }

    public function testTwoPresetsThatSetASharedSettingDifferentlyContradictEachOther(): void
    {
        $applier = new PresetApplier();

        $found = $applier->contradictions([$this->france(), $this->other()]);
        self::assertCount(1, $found);
        self::assertSame(['fr-FR', 'de-DE'], [$found[0]['first'], $found[0]['second']]);
        self::assertSame(['general.layout', 'general.renewal_months'], $found[0]['paths']);

        self::assertSame([], $applier->contradictions([$this->france()]), 'one preset cannot contradict itself');
        self::assertSame([], $applier->contradictions([$this->france(), $this->other(['general' => ['renewal_months' => 6, 'layout' => 'bar']])]), 'agreeing presets');
    }

    public function testASettingOnlyOnePresetSetsIsNotAContradiction(): void
    {
        $partial = Preset::fromDocument(['schema' => 'ccm-config/1', 'preset' => ['code' => 'es-ES'], 'texts' => ['es' => ['title' => 'Hola']]]);

        self::assertSame([], (new PresetApplier())->contradictions([$this->france(), $partial]));
    }
}
