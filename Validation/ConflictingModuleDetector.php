<?php

declare(strict_types=1);

namespace CookieConsentManager\Validation;

use Thelia\Model\ModuleQuery;

class ConflictingModuleDetector
{
    public function isActive(string $moduleCode): bool
    {
        return ModuleQuery::create()->filterByCode($moduleCode)->filterByActivate(1)->count() > 0;
    }
}
