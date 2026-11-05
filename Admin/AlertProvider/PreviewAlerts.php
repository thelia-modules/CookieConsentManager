<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin\AlertProvider;

use CookieConsentManager\Admin\Alert;
use CookieConsentManager\Front\PreviewToken;

/** The preview and the verification are signed with the secret of the shop: a weak one disables them. */
final readonly class PreviewAlerts implements AlertProvider
{
    public function __construct(
        private PreviewToken $tokens,
        private AlertText $text,
    ) {
    }

    public static function priority(): int
    {
        return 150;
    }

    public function alerts(array $draft): array
    {
        if ($this->tokens->isAvailable()) {
            return [];
        }

        return [new Alert(Alert::WARNING, 'preview_secret', $this->text->t('The preview and the verification are unavailable: the secret of the shop (APP_SECRET) is empty or shorter than %min% characters. Set a longer secret in the environment of the shop.', ['%min%' => (string) PreviewToken::MINIMUM_SECRET_BYTES]))];
    }
}
