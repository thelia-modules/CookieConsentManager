<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\ContrastChecker;
use PHPUnit\Framework\TestCase;

final class ContrastCheckerTest extends TestCase
{
    public function testKnownRatios(): void
    {
        self::assertEqualsWithDelta(21.0, ContrastChecker::ratio('#000000', '#ffffff'), 0.001);
        self::assertEqualsWithDelta(1.0, ContrastChecker::ratio('#777777', '#777777'), 0.001);
        self::assertEqualsWithDelta(4.48, ContrastChecker::ratio('#777777', '#ffffff'), 0.01);
    }

    public function testDefaultColorsPass(): void
    {
        $findings = (new ContrastChecker())->check(['primary' => '#1a56db', 'surface' => '#ffffff', 'text' => '#111827']);

        self::assertFalse(ContrastChecker::blocking($findings));
    }

    public function testALowContrastButtonBlocksAndComesWithACompliantColor(): void
    {
        $findings = (new ContrastChecker())->check(['primary' => '#9fc5f8', 'surface' => '#ffffff', 'text' => '#111827']);

        self::assertTrue(ContrastChecker::blocking($findings));
        $button = array_values(array_filter($findings, static fn ($finding): bool => 'button_on_surface' === $finding->id))[0];
        self::assertFalse($button->passes());
        self::assertNotNull($button->suggestion);

        $fixed = (new ContrastChecker())->check(['primary' => $button->suggestion, 'surface' => '#ffffff', 'text' => '#111827']);
        self::assertFalse(ContrastChecker::blocking($fixed), 'the proposed color satisfies every rule');
    }

    public function testTheProposalKeepsTheHueAndTheSaturationOfAPaleColor(): void
    {
        $findings = (new ContrastChecker())->check(['primary' => '#f5e6d3', 'surface' => '#ffffff', 'text' => '#111827']);
        $button = array_values(array_filter($findings, static fn ($finding): bool => 'button_on_surface' === $finding->id))[0];

        self::assertNotNull($button->suggestion);
        [$hue, $saturation, $lightness] = ContrastChecker::toHsl('#f5e6d3');
        [$newHue, $newSaturation, $newLightness] = ContrastChecker::toHsl($button->suggestion);
        self::assertEqualsWithDelta($hue, $newHue, 3.0);
        self::assertEqualsWithDelta($saturation, $newSaturation, 0.05, 'a brown stays brown, not grey');
        self::assertLessThan($lightness, $newLightness);
        self::assertFalse(ContrastChecker::blocking((new ContrastChecker())->check(['primary' => $button->suggestion, 'surface' => '#ffffff', 'text' => '#111827'])));
        $slightlyLighter = ContrastChecker::ratio('#ffffff', self::lighter($button->suggestion));
        self::assertLessThan(ContrastChecker::TEXT_MINIMUM, $slightlyLighter, 'the proposal is the smallest change that passes');
    }

    private static function lighter(string $hex): string
    {
        [$hue, $saturation, $lightness] = ContrastChecker::toHsl($hex);

        return (string) (new \ReflectionMethod(ContrastChecker::class, 'fromHsl'))->invoke(null, $hue, $saturation, $lightness + 0.01);
    }

    public function testTextBelowFourPointFiveBlocksButButtonRuleIsThree(): void
    {
        // #767676 on white is 4.54:1 (text ok); #8a8a8a is 3.45:1 (a button passes, a text does not).
        $checker = new ContrastChecker();
        self::assertFalse(ContrastChecker::blocking($checker->check(['primary' => '#1a56db', 'surface' => '#ffffff', 'text' => '#767676'])));
        self::assertTrue(ContrastChecker::blocking($checker->check(['primary' => '#1a56db', 'surface' => '#ffffff', 'text' => '#8a8a8a'])));
    }

    public function testHexValidation(): void
    {
        self::assertTrue(ContrastChecker::isHex('#1A56db'));
        foreach (['1a56db', '#fff', '#12345g', 'red'] as $invalid) {
            self::assertFalse(ContrastChecker::isHex($invalid));
        }
    }
}
