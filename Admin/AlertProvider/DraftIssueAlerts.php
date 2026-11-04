<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin\AlertProvider;

use CookieConsentManager\Admin\Alert;
use CookieConsentManager\Admin\FieldLabeler;
use CookieConsentManager\Validation\DraftValidator;
use CookieConsentManager\Validation\ValidationIssue;

/** The blocking and warning issues of the validator, each as an alert pointing at the screen to fix. */
final readonly class DraftIssueAlerts implements AlertProvider
{
    public function __construct(
        private DraftValidator $validator,
        private FieldLabeler $labels,
        private AlertText $text,
    ) {
    }

    public static function priority(): int
    {
        return 1000;
    }

    public function alerts(array $draft): array
    {
        $alerts = [];

        foreach ($this->validator->validate($draft) as $issue) {
            if ('environment_unconfirmed' === $issue->code) {
                $alerts[] = new Alert(Alert::DANGER, 'environment_unconfirmed', $this->text->t('"%field%" was imported from another site and is not confirmed yet: check it for this shop.', ['%field%' => $this->labels->label($issue->field, $this->text->locale(), $draft)]), 'ccm_admin_versions');
            } elseif ('policy_missing' === $issue->code) {
                $alerts[] = new Alert(Alert::DANGER, 'policy_missing', $this->text->t('No privacy policy page is set: create it (Folders and Pages menu of the back-office), then enter its address in the "Privacy policy URL" field.'), 'ccm_admin_dashboard');
            } elseif (\in_array($issue->code, ['implicit_consent', 'label_gap'], true)) {
                $alerts[] = new Alert(Alert::DANGER, $issue->code.'_'.str_replace('.', '_', $issue->field), $this->text->t('Draft, %field%: %message%', ['%field%' => $this->labels->label($issue->field, $this->text->locale(), $draft), '%message%' => $issue->message]), 'ccm_admin_appearance');
            } elseif ($issue->isError()) {
                $alerts[] = new Alert(Alert::DANGER, 'draft_'.$issue->code, $this->text->t('Draft, %field%: %message%', ['%field%' => $this->labels->label($issue->field, $this->text->locale(), $draft), '%message%' => $issue->message]), $this->routeFor($issue));
            }
        }

        return $alerts;
    }

    private function routeFor(ValidationIssue $issue): string
    {
        return match (true) {
            \in_array($issue->code, ['policy_url_terms', 'policy_url_preset_missing'], true) => 'ccm_admin_policy_template',
            str_starts_with($issue->field, 'services.'), str_starts_with($issue->field, 'exempt_measurement') => 'ccm_admin_services',
            str_starts_with($issue->field, 'consent_mode') => 'ccm_admin_consent_mode',
            default => 'ccm_admin_dashboard',
        };
    }
}
