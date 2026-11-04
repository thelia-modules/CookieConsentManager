<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Fixtures\Extension;

use CookieConsentManager\Admin\Alert;
use CookieConsentManager\Admin\AlertProvider\AlertProvider;

final class EarlyThirdPartyAlertProvider implements AlertProvider
{
    public static function priority(): int
    {
        return 5000;
    }

    public function alerts(array $draft): array
    {
        return [new Alert(Alert::INFO, 'early_info', 'runs before the others')];
    }
}
