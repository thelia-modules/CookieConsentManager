<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

/** Lists the module templates a set of theme directories provides, with the contract version each declares. */
final class OverrideScanner
{
    public const DIRECTORY = 'CookieConsentManager';
    private const MARKER = '/\A\s*\{#\s*ccm-templates:(\S+?)\s*#\}/';

    /**
     * @param array<string, string> $themes theme name => absolute path, the closest to the active theme first
     *
     * @return list<array{template: string, source: string, hash: string, contract: string|null}>
     */
    public function scan(array $themes): array
    {
        $overridden = [];

        foreach ($themes as $name => $path) {
            foreach (glob($path.'/'.self::DIRECTORY.'/*.twig') ?: [] as $file) {
                $template = basename($file);
                // The theme closest to the active one wins, as in the Twig loader.
                $overridden[$template] ??= [
                    'template' => $template,
                    'source' => (string) $name,
                    'hash' => substr((string) hash_file('sha256', $file), 0, 12),
                    'contract' => self::contract((string) file_get_contents($file)),
                ];
            }
        }

        ksort($overridden);

        return array_values($overridden);
    }

    public static function contract(string $source): ?string
    {
        return 1 === preg_match(self::MARKER, $source, $match) ? $match[1] : null;
    }
}
