<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin\AlertProvider;

use CookieConsentManager\Admin\ActiveLanguages;
use CookieConsentManager\Admin\Alert;
use CookieConsentManager\Admin\FieldLabeler;
use CookieConsentManager\Front\FrontTexts;

/** An active language without banner text or with an incomplete translation. */
final readonly class LanguageAlerts implements AlertProvider
{
    public function __construct(
        private ActiveLanguages $languages,
        private FieldLabeler $labels,
        private AlertText $text,
    ) {
    }

    public static function priority(): int
    {
        return 700;
    }

    public function alerts(array $draft): array
    {
        $alerts = [];

        $this->languages($draft, $alerts);

        return $alerts;
    }

    /**
     * @param array<string, mixed> $draft
     * @param list<Alert>          $alerts
     */
    private function languages(array $draft, array &$alerts): void
    {
        foreach ($this->languages->codes() as $code) {
            $provided = FrontTexts::supports($code);
            if (!isset($draft['texts'][$code]) && !$provided) {
                $alerts[] = new Alert(Alert::DANGER, 'language_without_text', $this->text->t('The language "%code%" is active but has no banner text (English is shown).', ['%code%' => $code]), 'ccm_admin_appearance');
                continue;
            }

            $missing = [];
            foreach ($provided ? [] : ['title', 'body', 'accept', 'reject', 'customize', 'close', 'allow', 'preferences'] as $field) {
                if ('' === trim((string) ($draft['texts'][$code][$field] ?? ''))) {
                    $missing[] = $field;
                }
            }
            foreach ($draft['purposes'] as $purpose) {
                if ($provided && isset(FrontTexts::DEFAULT_PURPOSES[$purpose['code']])) {
                    continue;
                }
                if ('' === trim((string) ($purpose['label'][$code] ?? '')) || '' === trim((string) ($purpose['description'][$code] ?? ''))) {
                    $missing[] = 'purposes.'.$purpose['code'];
                }
            }
            if ([] !== $missing) {
                $alerts[] = new Alert(Alert::DANGER, 'language_incomplete_'.$code, $this->text->t('The translation "%code%" is incomplete: the missing texts are shown in English.', ['%code%' => $code]), 'ccm_admin_appearance', array_map(fn (string $path): string => $this->labels->label(str_starts_with($path, 'purposes.') ? $path : 'texts.'.$code.'.'.$path, $this->text->locale(), $draft), $missing));
            }
        }
    }
}
