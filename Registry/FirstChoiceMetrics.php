<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

final class FirstChoiceMetrics
{
    public const ROBOT = 'first_robot';
    public const REJECT_PANEL = 'first_reject_panel';

    /**
     * @param array<string, int> $purposes
     * @param array<string, int> $services
     *
     * @return list<string>
     */
    public static function of(string $action, array $purposes, array $services): array
    {
        $metrics = ['first_total', match ($action) {
            'accept_all' => 'first_accept_all',
            'reject_all', 'close_reject' => 'first_reject_all',
            'custom' => [] === array_filter($purposes) && [] === array_filter($services) ? self::REJECT_PANEL : 'first_custom',
            default => 'first_custom',
        }];

        foreach ($purposes as $code => $state) {
            if (1 === $state) {
                $metrics[] = 'purpose:'.$code;
            }
        }
        foreach ($services as $key => $state) {
            if (1 === $state) {
                $metrics[] = 'service:'.$key;
            }
        }

        return $metrics;
    }
}
