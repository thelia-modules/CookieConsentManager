<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Support;

use CookieConsentManager\Publication\OverriddenTemplatesProvider;

final readonly class NoOverriddenTemplates implements OverriddenTemplatesProvider
{
    public function list(): array
    {
        return [];
    }

    public function signature(): string
    {
        return '';
    }
}
