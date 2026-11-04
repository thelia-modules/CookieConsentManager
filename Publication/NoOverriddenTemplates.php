<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

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
