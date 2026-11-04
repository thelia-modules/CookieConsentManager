<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

/**
 * WCAG 2.x contrast of the banner colors (SPEC 9.3): text 4.5:1, button 3:1.
 * The button label is assumed white on the primary color, as the Flexy banner renders it.
 */
final class ContrastChecker
{
    public const TEXT_MINIMUM = 4.5;
    public const BUTTON_MINIMUM = 3.0;
    public const LABEL_COLOR = '#ffffff';

    public static function isHex(string $color): bool
    {
        return 1 === preg_match('/^#[0-9a-fA-F]{6}$/', $color);
    }

    public static function ratio(string $first, string $second): float
    {
        $a = self::luminance($first);
        $b = self::luminance($second);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * @param array{primary: string, surface: string, text: string} $colors
     *
     * @return list<ContrastFinding>
     */
    public function check(array $colors): array
    {
        $primary = strtolower($colors['primary']);
        $surface = strtolower($colors['surface']);
        $text = strtolower($colors['text']);

        $textOk = static fn (string $candidate): bool => self::ratio($candidate, $surface) >= self::TEXT_MINIMUM;
        $buttonOk = static fn (string $candidate): bool => self::ratio($candidate, $surface) >= self::BUTTON_MINIMUM
            && self::ratio(self::LABEL_COLOR, $candidate) >= self::TEXT_MINIMUM;

        return [
            new ContrastFinding('text_on_surface', 'text', $text, $surface, self::ratio($text, $surface), self::TEXT_MINIMUM, $this->suggest($text, $surface, $textOk)),
            new ContrastFinding('button_on_surface', 'primary', $primary, $surface, self::ratio($primary, $surface), self::BUTTON_MINIMUM, $this->suggest($primary, $surface, $buttonOk)),
            new ContrastFinding('label_on_button', 'primary', self::LABEL_COLOR, $primary, self::ratio(self::LABEL_COLOR, $primary), self::TEXT_MINIMUM, $this->suggest($primary, $surface, $buttonOk)),
        ];
    }

    /** @param list<ContrastFinding> $findings */
    public static function blocking(array $findings): bool
    {
        foreach ($findings as $finding) {
            if (!$finding->passes()) {
                return true;
            }
        }

        return false;
    }

    /** Same hue and saturation, lightness moved by the smallest amount that makes the rule hold. */
    private function suggest(string $color, string $surface, callable $accepts): ?string
    {
        if ($accepts($color)) {
            return null;
        }

        [$hue, $saturation, $lightness] = self::toHsl($color);
        for ($step = 1; $step <= 200; ++$step) {
            foreach ([-1, 1] as $direction) {
                $candidateLightness = $lightness + $direction * $step * 0.005;
                if ($candidateLightness < 0.0 || $candidateLightness > 1.0) {
                    continue;
                }
                $candidate = self::fromHsl($hue, $saturation, $candidateLightness);
                if ($accepts($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /** @return array{0: float, 1: float, 2: float} hue in degrees, saturation and lightness in 0..1 */
    public static function toHsl(string $hex): array
    {
        [$r, $g, $b] = array_map(static fn (int $channel): float => $channel / 255, self::channels($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $lightness = ($max + $min) / 2;
        $delta = $max - $min;
        if (0.0 === $delta) {
            return [0.0, 0.0, $lightness];
        }

        $saturation = $delta / (1 - abs(2 * $lightness - 1));
        $hue = match ($max) {
            $r => 60 * fmod(($g - $b) / $delta, 6),
            $g => 60 * (($b - $r) / $delta + 2),
            default => 60 * (($r - $g) / $delta + 4),
        };

        return [$hue < 0 ? $hue + 360 : $hue, $saturation, $lightness];
    }

    private static function fromHsl(float $hue, float $saturation, float $lightness): string
    {
        $chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
        $x = $chroma * (1 - abs(fmod($hue / 60, 2) - 1));
        $m = $lightness - $chroma / 2;
        [$r, $g, $b] = match (true) {
            $hue < 60 => [$chroma, $x, 0.0],
            $hue < 120 => [$x, $chroma, 0.0],
            $hue < 180 => [0.0, $chroma, $x],
            $hue < 240 => [0.0, $x, $chroma],
            $hue < 300 => [$x, 0.0, $chroma],
            default => [$chroma, 0.0, $x],
        };

        return \sprintf('#%02x%02x%02x', (int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function channels(string $hex): array
    {
        return [(int) hexdec(substr($hex, 1, 2)), (int) hexdec(substr($hex, 3, 2)), (int) hexdec(substr($hex, 5, 2))];
    }

    private static function luminance(string $hex): float
    {
        $linear = array_map(static function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, self::channels($hex));

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
