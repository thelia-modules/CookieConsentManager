<?php

declare(strict_types=1);

namespace CookieConsentManager\Preset;

use CookieConsentManager\Publication\ConfigDiff;

/**
 * Applies a preset to a draft for one language. The texts, the purposes' wording and the
 * attachment belong to that language; the legal settings shared by every language follow
 * the preset attached to the main language of the shop, so they are written only when the
 * target language is the main one. Keys the preset does not carry are left untouched.
 */
final class PresetApplier
{
    /** Sections whose values apply to the whole shop. */
    private const COMMON_SECTIONS = ['general', 'consent_mode', 'registry'];

    /**
     * @param array<string, mixed> $draft normalized
     *
     * @return array<string, mixed> the candidate draft, not normalized
     */
    public function apply(array $draft, Preset $preset, string $language, bool $mainLanguage): array
    {
        $document = $preset->document;
        $source = $preset->language();

        if (isset($document['texts'][$source])) {
            $draft['texts'][$language] = array_replace($draft['texts'][$language] ?? [], $document['texts'][$source]);
        }

        $wording = array_column((array) ($document['purposes'] ?? []), null, 'code');
        foreach ($draft['purposes'] as $index => $purpose) {
            if (isset($wording[$purpose['code']])) {
                foreach (['label', 'description'] as $field) {
                    if (isset($wording[$purpose['code']][$field][$source])) {
                        $draft['purposes'][$index][$field][$language] = $wording[$purpose['code']][$field][$source];
                    }
                }
            }
        }

        if ($mainLanguage) {
            foreach (self::COMMON_SECTIONS as $section) {
                foreach ((array) ($document[$section] ?? []) as $key => $value) {
                    $draft[$section][$key] = $value;
                }
            }
            if (isset($document['policy']['page_title'])) {
                $draft['policy']['page_title'] = $document['policy']['page_title'];
            }
            if (isset($document['exempt_measurement'])) {
                $draft['exempt_measurement'] = $document['exempt_measurement'];
            }
        }

        $draft['presets'][$language] = $preset->code;

        return $draft;
    }

    /**
     * The settings shared by every language that two attached presets set differently.
     *
     * @param list<Preset> $presets distinct presets attached to the languages of the shop
     *
     * @return list<array{first: string, second: string, paths: list<string>}> codes of the two presets and the paths they disagree on
     */
    public function contradictions(array $presets): array
    {
        $contradictions = [];
        for ($i = 0, $count = \count($presets); $i < $count; ++$i) {
            for ($j = $i + 1; $j < $count; ++$j) {
                $first = $this->common($presets[$i]);
                $second = $this->common($presets[$j]);
                $paths = array_values(array_filter(
                    array_keys(array_intersect_key($first, $second)),
                    static fn (string $path): bool => $first[$path] !== $second[$path],
                ));
                if ([] !== $paths) {
                    $contradictions[] = ['first' => $presets[$i]->code, 'second' => $presets[$j]->code, 'paths' => $paths];
                }
            }
        }

        return $contradictions;
    }

    /** @return array<string, scalar|null> */
    private function common(Preset $preset): array
    {
        $common = [];
        foreach ([...self::COMMON_SECTIONS, 'exempt_measurement'] as $section) {
            if (isset($preset->document[$section])) {
                $common += ConfigDiff::flatten([$section => $preset->document[$section]]);
            }
        }
        if (isset($preset->document['policy']['page_title'])) {
            $common['policy.page_title'] = $preset->document['policy']['page_title'];
        }

        return $common;
    }
}
