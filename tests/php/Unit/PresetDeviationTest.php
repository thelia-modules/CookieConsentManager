<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Draft\DraftNormalizer;
use CookieConsentManager\Preset\Preset;
use CookieConsentManager\Preset\PresetDeviation;
use PHPUnit\Framework\TestCase;

final class PresetDeviationTest extends TestCase
{
    private Preset $preset;

    protected function setUp(): void
    {
        $this->preset = Preset::fromDocument([
            'schema' => 'ccm-config/1',
            'preset' => ['code' => 'fr-FR'],
            'general' => ['renewal_months' => 6, 'block_embedded_iframes' => true, 'reload_on_script_withdraw' => true],
            'consent_mode' => ['mode' => 'basic', 'ads_data_redaction' => true, 'url_passthrough' => false],
            'registry' => ['retention_months' => 25, 'ip_mode' => 'none'],
        ]);
    }

    public function testADraftThatFollowsThePresetDeviatesFromNothing(): void
    {
        $draft = (new DraftNormalizer())->normalize(['general' => ['renewal_months' => 3], 'registry' => ['retention_months' => 12], 'consent_mode' => ['ads_data_redaction' => true]]);

        self::assertSame([], (new PresetDeviation())->between($draft, $this->preset), 'stricter than the preset is not a deviation');
    }

    public function testEachLessProtectiveSettingIsNamedWithTheValueOfThePreset(): void
    {
        $draft = (new DraftNormalizer())->normalize([
            'general' => ['renewal_months' => 13, 'block_embedded_iframes' => false, 'reload_on_script_withdraw' => false],
            'consent_mode' => ['mode' => 'advanced', 'ads_data_redaction' => false, 'url_passthrough' => true],
            'registry' => ['retention_months' => 60, 'ip_mode' => 'truncated'],
        ]);

        $found = array_column((new PresetDeviation())->between($draft, $this->preset), null, 'path');

        self::assertSame([
            'general.renewal_months', 'general.reload_on_script_withdraw', 'general.block_embedded_iframes', 'consent_mode.mode', 'consent_mode.ads_data_redaction', 'consent_mode.url_passthrough', 'registry.retention_months', 'registry.ip_mode',
        ], array_keys($found));
        self::assertSame(['path' => 'general.block_embedded_iframes', 'current' => false, 'reference' => true], $found['general.block_embedded_iframes']);
        self::assertSame(['path' => 'registry.retention_months', 'current' => 60, 'reference' => 25], $found['registry.retention_months']);
    }

    public function testASettingThePresetDoesNotCarryIsNeverCompared(): void
    {
        $preset = Preset::fromDocument(['schema' => 'ccm-config/1', 'preset' => ['code' => 'xx-XX'], 'general' => ['renewal_months' => 6]]);
        $draft = (new DraftNormalizer())->normalize(['general' => ['renewal_months' => 6, 'block_embedded_iframes' => false], 'registry' => ['retention_months' => 60]]);

        self::assertSame([], (new PresetDeviation())->between($draft, $preset));
    }
}
