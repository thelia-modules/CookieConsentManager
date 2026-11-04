<?php

declare(strict_types=1);

namespace CookieConsentManager\Draft;

use CookieConsentManager\Front\FrontTexts;

final class DefaultSettings
{
    public const PURPOSES_WITH_SIGNALS = [
        'analytics' => ['analytics_storage'],
        'ads' => ['ad_storage', 'ad_user_data', 'ad_personalization'],
        'social' => [],
        'media' => [],
        'support' => [],
        'personalization' => ['functionality_storage', 'personalization_storage'],
    ];
    public const SIGNALS = [
        'ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage',
        'functionality_storage', 'personalization_storage', 'security_storage',
    ];
    public const EXEMPT_CONDITIONS = [
        'audience_measurement_only', 'no_cross_site_tracking', 'no_third_party_sharing',
        'limited_retention', 'opposition_offered',
    ];

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return [
            'general' => [
                'enabled' => true,
                'layout' => 'bar',
                'close_button' => false,
                'floating_button' => false,
                'reload_on_script_withdraw' => true,
                'block_embedded_iframes' => true,
                'experimental_services' => false,
                'renewal_months' => 6,
            ],
            'policy' => ['url' => null, 'page_title' => null, 'urls' => []],
            'texts' => ['en' => FrontTexts::DEFAULT_TEXTS],
            'appearance' => ['colors' => ['primary' => '#1a56db', 'surface' => '#ffffff', 'text' => '#111827']],
            'purposes' => self::purposes(),
            'consent_mode' => [
                'enabled' => true,
                'mode' => 'basic',
                'ads_data_redaction' => false,
                'url_passthrough' => false,
                'wait_for_update_ms' => 500,
                'regions' => [],
                'uet' => ['enabled' => false],
            ],
            'exempt_measurement' => [],
            'registry' => ['retention_months' => 60, 'ip_mode' => 'none', 'link_customer' => false],
            'presets' => [],
            'services' => [],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function purposes(): array
    {
        $purposes = [];
        $order = 1;
        foreach (self::PURPOSES_WITH_SIGNALS as $code => $signals) {
            $purposes[] = [
                'code' => $code,
                'order' => $order++,
                'consent_mode' => $signals,
                'label' => ['en' => FrontTexts::DEFAULT_PURPOSES[$code]['label']],
                'description' => ['en' => FrontTexts::DEFAULT_PURPOSES[$code]['description']],
            ];
        }

        return $purposes;
    }
}
