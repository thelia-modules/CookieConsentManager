<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

/** English wording of the codes the back-office shows (registry actions and sources, exemption conditions, Consent Mode). */
final class CodeLabels
{
    public const GROUPS = [
        'action' => [
            'accept_all' => 'Accept all',
            'reject_all' => 'Reject all',
            'custom' => 'Custom choice',
            'service_allow' => 'Service allowed',
            'withdraw' => 'Withdrawal',
            'close_reject' => 'Banner closed (refusal)',
            'exempt_optout' => 'Opposition to the exempted measurement',
            'renew' => 'Renewal',
        ],
        'source' => [
            'banner' => 'Banner',
            'preferences' => 'Preferences panel',
            'placeholder' => 'Placeholder',
            'api' => 'API',
        ],
        'condition' => [
            'audience_measurement_only' => 'Audience measurement only',
            'no_cross_site_tracking' => 'No cross-site tracking',
            'no_third_party_sharing' => 'No sharing with third parties',
            'limited_retention' => 'Limited retention',
            'opposition_offered' => 'Opposition offered to the visitor',
        ],
        'condition_help' => [
            'audience_measurement_only' => 'The tool only measures the audience of the site: no advertising, no profiling.',
            'no_cross_site_tracking' => 'The data are not combined with other sites or other tools.',
            'no_third_party_sharing' => 'The tool provider does not reuse the data for its own purposes.',
            'limited_retention' => 'Visitor identifiers last 13 months at most and the data 25 months at most.',
            'opposition_offered' => 'The visitor can refuse the measurement easily, and it is announced to them.',
        ],
        'signal' => [
            'ad_storage' => 'Advertising storage',
            'ad_user_data' => 'Advertising user data',
            'ad_personalization' => 'Advertising personalization',
            'analytics_storage' => 'Analytics storage',
            'functionality_storage' => 'Functionality storage',
            'personalization_storage' => 'Personalization storage',
            'security_storage' => 'Security storage',
        ],
        'signal_state' => [
            'denied' => 'Denied',
            'granted' => 'Granted',
        ],
    ];

    public static function english(string $group, string $code): string
    {
        return self::GROUPS[$group][$code] ?? $code;
    }
}
