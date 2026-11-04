<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin\AlertProvider;

use CookieConsentManager\Admin\Alert;

/** Consent Mode and renewal delay settings that protect visitors less than expected. */
final readonly class ConsentSettingsAlerts implements AlertProvider
{
    public function __construct(
        private AlertText $text,
    ) {
    }

    public static function priority(): int
    {
        return 400;
    }

    public function alerts(array $draft): array
    {
        $alerts = [];

        if ('advanced' === $draft['consent_mode']['mode']) {
            $alerts[] = new Alert(Alert::DANGER, 'advanced_mode', $this->text->t('Consent Mode is advanced: Google tags load before the visitor chooses and send pings (screen size, browser data) even after a refusal. No authority has validated this mode for visitors in France: get your legal contact\'s written agreement, or go back to the basic mode.'), 'ccm_admin_consent_mode');
        }

        if ($draft['general']['renewal_months'] > 6) {
            $alerts[] = new Alert(Alert::WARNING, 'renewal_long', $this->text->t('The choice is asked again after %months% months: the CNIL recommends 6 months at most.', ['%months%' => (string) $draft['general']['renewal_months']]), 'ccm_admin_dashboard', [], [], 'general_renewal');
        }

        return $alerts;
    }
}
