<?php

declare(strict_types=1);

namespace CookieConsentManager\Validation;

use CookieConsentManager\Admin\ActiveLanguages;

final readonly class FrenchPresetLanguages
{
    public function __construct(private ActiveLanguages $languages)
    {
    }

    /**
     * The languages whose cookie policy address the French preset checks: those it is attached to, and every active language when it is attached to the main one.
     *
     * @param array<string, mixed> $draft normalized
     *
     * @return list<string>
     */
    public function of(array $draft): array
    {
        $languages = array_keys(array_filter($draft['presets'], static fn (string $code): bool => DraftValidator::FRANCE_PRESET === $code));
        if (DraftValidator::FRANCE_PRESET === ($draft['presets'][$this->languages->mainCode()] ?? null)) {
            $languages = [...$languages, ...$this->languages->codes()];
        }

        return array_values(array_unique(array_map('strval', $languages)));
    }
}
