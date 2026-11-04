<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

/** Lists the banner templates a theme overrides; implemented by the front lot. */
interface OverriddenTemplatesProvider
{
    /** @return list<array{template: string, source: string, hash: string, contract: string|null}> contract: version read from the {# ccm-templates:N #} marker, null without one */
    public function list(): array;
}
