<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Fixtures\Extension;

use CookieConsentManager\Admin\Alert;
use CookieConsentManager\Admin\AlertProvider\AlertProvider;

final class ThirdPartyAlertProvider implements AlertProvider
{
    public static function priority(): int
    {
        return 50;
    }

    public function alerts(array $draft): array
    {
        return [new Alert(Alert::INFO, 'third_party_info', 'from a third-party module')];
    }
}
