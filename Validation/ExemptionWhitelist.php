<?php

declare(strict_types=1);

namespace CookieConsentManager\Validation;

final class ExemptionWhitelist
{
    /** @var array<string, string> catalogue key => English message of the label shown in the back-office */
    public const SERVICES = [
        'matomo' => 'Self-hosted Matomo: anonymised IP, 13-month cookie not extended, no User ID',
        'wysistathightrack' => 'Wysistat in CNIL-exempt mode (to be set in your Wysistat account)',
        'plausible' => 'Plausible: no cookie, hosted in the EU, no identifying data',
        'simpleanalytics' => 'Simple Analytics: no cookie, no event or identifying data',
    ];

    public static function allows(string $serviceKey): bool
    {
        return \array_key_exists($serviceKey, self::SERVICES);
    }
}
