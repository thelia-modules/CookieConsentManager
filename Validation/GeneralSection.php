<?php

declare(strict_types=1);

namespace CookieConsentManager\Validation;

use CookieConsentManager\Draft\DefaultSettings;

/** Layout, delays, registry, policy addresses, banner texts and Consent Mode regions. */
final readonly class GeneralSection implements SectionValidator
{
    private const IMPLICIT_CONSENT = [
        '/\ben\s+(poursuivant|continuant|naviguant)\b/iu',
        '/\bpoursuite\s+de\s+(votre|la)\s+navigation\b/iu',
        '/\bby\s+(continuing|browsing|navigating|proceeding|using\s+this\s+(web)?site)\b/i',
        '/\bcontinuing\s+to\s+(browse|use|navigate)\b/i',
    ];
    private const EUROPEAN_REGIONS = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'IS', 'LI', 'NO', 'GB', 'CH', 'EU', 'EEA'];

    public function __construct(
        private IssueFactory $issues,
    ) {
    }

    public static function priority(): int
    {
        return 1000;
    }

    public function validate(array $draft): array
    {
        $issues = [];
        $this->general($draft, $issues);

        return $issues;
    }

    /**
     * @param array<string, mixed>  $draft
     * @param list<ValidationIssue> $issues
     */
    private function general(array $draft, array &$issues): void
    {
        $general = $draft['general'];
        if (!\in_array($general['layout'], ['bar', 'modal'], true)) {
            $issues[] = $this->issues->error('layout', 'general.layout', 'The layout must be "bar" or "modal".');
        }
        if ($general['renewal_months'] < 1 || $general['renewal_months'] > 13) {
            $issues[] = $this->issues->error('renewal_months', 'general.renewal_months', 'The renewal delay must be between 1 and 13 months.');
        }
        if (!\in_array($draft['consent_mode']['mode'], ['basic', 'advanced'], true)) {
            $issues[] = $this->issues->error('consent_mode', 'consent_mode.mode', 'The Consent Mode must be "basic" or "advanced".');
        }
        if (!\in_array($draft['registry']['ip_mode'], ['none', 'truncated'], true)) {
            $issues[] = $this->issues->error('ip_mode', 'registry.ip_mode', 'The IP option must be "none" or "truncated".');
        }
        if ($draft['registry']['retention_months'] < 1) {
            $issues[] = $this->issues->error('retention', 'registry.retention_months', 'The retention must be at least one month.');
        } elseif ($draft['registry']['retention_months'] > DraftValidator::MAX_RETENTION_MONTHS) {
            $issues[] = $this->issues->error('retention_max', 'registry.retention_months', 'The retention cannot exceed %max% months.', ['%max%' => (string) DraftValidator::MAX_RETENTION_MONTHS]);
        }
        $this->policyUrls($draft, $issues);
        $this->texts($draft, $issues);
        if (null === $draft['policy']['url'] || '' === $draft['policy']['url']) {
            $issues[] = new ValidationIssue(ValidationIssue::WARNING, 'policy_missing', 'policy.url', $this->issues->trans('No privacy policy page is set.'));
        }
        foreach ($draft['consent_mode']['regions'] as $index => $region) {
            foreach ((array) ($region['default'] ?? []) as $signal => $state) {
                if (!\in_array($signal, DefaultSettings::SIGNALS, true)) {
                    $issues[] = $this->issues->error('signal', 'consent_mode.regions.'.$index, 'Unknown Consent Mode signal "%signal%".', ['%signal%' => $signal]);
                } elseif ('granted' === $state && 'security_storage' !== $signal && self::coversEurope((array) $region['iso'])) {
                    $issues[] = $this->issues->error('default_granted', 'consent_mode.regions.'.$index, 'The default "granted" for "%signal%" is refused for a region that includes the European Union or the United Kingdom: it equals a tracker set without consent.', ['%signal%' => $signal]);
                }
            }
        }
    }

    /**
     * @param array<string, mixed>  $draft
     * @param list<ValidationIssue> $issues
     */
    private function policyUrls(array $draft, array &$issues): void
    {
        $urls = ['policy.url' => (string) ($draft['policy']['url'] ?? '')];
        foreach ((array) ($draft['policy']['urls'] ?? []) as $language => $url) {
            $urls['policy.urls.'.$language] = (string) $url;
        }
        foreach ($urls as $field => $url) {
            if ('' !== $url && !DraftValidator::isPolicyUrl($url)) {
                $issues[] = $this->issues->error('policy_url_invalid', $field, 'The address "%url%" is not valid: enter a path of the shop (/page) or an absolute https:// address.', ['%url%' => $url]);
            }
        }
        if (mb_strlen((string) ($draft['policy']['page_title'] ?? '')) > 200) {
            $issues[] = $this->issues->error('too_long', 'policy.page_title', 'The text is too long (%max% characters at most).', ['%max%' => '200']);
        }
    }

    /**
     * @param array<string, mixed>  $draft
     * @param list<ValidationIssue> $issues
     */
    private function texts(array $draft, array &$issues): void
    {
        foreach ($draft['texts'] as $language => $row) {
            foreach (DraftValidator::TEXT_LIMITS as $key => $limit) {
                $field = 'texts.'.$language.'.'.$key;
                $value = trim((string) ($row[$key] ?? ''));
                if (\in_array($key, DraftValidator::REQUIRED_TEXTS, true) && '' === $value) {
                    $issues[] = $this->issues->error('text_empty', $field, 'This label cannot be empty.');
                } elseif (mb_strlen($value) > $limit) {
                    $issues[] = $this->issues->error('too_long', $field, 'The text is too long (%max% characters at most).', ['%max%' => (string) $limit]);
                }
            }

            foreach (['title', 'body', 'free_text'] as $key) {
                foreach (self::IMPLICIT_CONSENT as $pattern) {
                    if (1 === preg_match($pattern, (string) ($row[$key] ?? ''))) {
                        $issues[] = new ValidationIssue(ValidationIssue::WARNING, 'implicit_consent', 'texts.'.$language.'.'.$key, $this->issues->trans('This text suggests that continuing to browse is a consent: it is not one. Remove it.'));
                        break;
                    }
                }
            }

            $accept = mb_strlen(trim((string) ($row['accept'] ?? '')));
            $reject = mb_strlen(trim((string) ($row['reject'] ?? '')));
            if ($accept > 0 && $reject > 0 && abs($accept - $reject) / max($accept, $reject) > 0.5) {
                $issues[] = new ValidationIssue(ValidationIssue::WARNING, 'label_gap', 'texts.'.$language.'.reject', $this->issues->trans('The Accept and Reject labels differ by more than 50 % in length: the two choices must look equal.'));
            }
        }
    }

    /** @param list<string> $iso an empty list is the global default */
    private static function coversEurope(array $iso): bool
    {
        if ([] === $iso) {
            return true;
        }
        foreach ($iso as $code) {
            if (\in_array(strtoupper(explode('-', $code)[0]), self::EUROPEAN_REGIONS, true)) {
                return true;
            }
        }

        return false;
    }
}
