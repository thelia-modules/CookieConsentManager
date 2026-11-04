<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin\AlertProvider;

use CookieConsentManager\Admin\Alert;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contributes alerts to the dashboard. Any autoconfigured service implementing it is picked up by AlertCollector.
 * Alerts are sorted by severity, then kept in the order of the providers (decreasing priority).
 */
#[AutoconfigureTag(self::TAG)]
interface AlertProvider
{
    public const TAG = 'ccm.alert_provider';

    /** Used when the tag carries no explicit priority: higher runs first. */
    public static function priority(): int;

    /**
     * @param array<string, mixed> $draft normalized draft
     *
     * @return list<Alert>
     */
    public function alerts(array $draft): array;
}
