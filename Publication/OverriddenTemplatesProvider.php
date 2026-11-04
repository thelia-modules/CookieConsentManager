<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

/** Lists the banner templates a theme overrides; implemented by the front lot. */
interface OverriddenTemplatesProvider
{
    /** @return list<array{template: string, source: string, hash: string, contract: string|null}> contract: version read from the {# ccm-templates:N #} marker, null without one */
    public function list(): array;

    /** Cheap fingerprint of what `list()` reads (paths, modification times, sizes): changes whenever an override is added, edited or removed, without reading or hashing any template. */
    public function signature(): string;
}
