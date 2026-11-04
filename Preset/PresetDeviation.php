<?php

declare(strict_types=1);

namespace CookieConsentManager\Preset;

/** The settings shared by every language that the draft sets less protectively than the preset of the main language. */
final class PresetDeviation
{
    /** @var array<string, 'lower'|'true'|'false'|list<string>> path => what protects most: the lowest number, a boolean, or the first of an ordered list */
    private const PROTECTIVE = [
        'general.renewal_months' => 'lower',
        'general.reload_on_script_withdraw' => 'true',
        'general.block_embedded_iframes' => 'true',
        'consent_mode.mode' => ['basic', 'advanced'],
        'consent_mode.ads_data_redaction' => 'true',
        'consent_mode.url_passthrough' => 'false',
        'registry.retention_months' => 'lower',
        'registry.ip_mode' => ['none', 'truncated'],
    ];

    /**
     * @param array<string, mixed> $draft normalized
     *
     * @return list<array{path: string, current: scalar|null, reference: scalar|null}>
     */
    public function between(array $draft, Preset $preset): array
    {
        $deviations = [];
        foreach (self::PROTECTIVE as $path => $rule) {
            $reference = self::read($preset->document, $path);
            $current = self::read($draft, $path);
            if (null === $reference || null === $current || !self::lessProtective($rule, $current, $reference)) {
                continue;
            }
            $deviations[] = ['path' => $path, 'current' => $current, 'reference' => $reference];
        }

        return $deviations;
    }

    /** @param array<string, mixed> $document */
    private static function read(array $document, string $path): mixed
    {
        foreach (explode('.', $path) as $segment) {
            if (!\is_array($document) || !\array_key_exists($segment, $document)) {
                return null;
            }
            $document = $document[$segment];
        }

        return $document;
    }

    /** @param 'lower'|'true'|'false'|list<string> $rule */
    private static function lessProtective(string|array $rule, mixed $current, mixed $reference): bool
    {
        return match (true) {
            'lower' === $rule => (int) $current > (int) $reference,
            'true' === $rule => !$current && (bool) $reference,
            'false' === $rule => (bool) $current && !$reference,
            default => array_search($current, $rule, true) > array_search($reference, $rule, true),
        };
    }
}
