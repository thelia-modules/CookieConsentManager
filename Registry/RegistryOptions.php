<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

use CookieConsentManager\Draft\DefaultSettings;
use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Publication\VersionRepository;

/** The registry options in force: those of the published version, since that is what visitors were told. The draft only before a first publication. */
final readonly class RegistryOptions
{
    public function __construct(
        private DraftRepository $drafts,
        private VersionRepository $versions,
    ) {
    }

    /** @return array{retention_months: int, ip_mode: string} */
    public function current(): array
    {
        $latest = $this->versions->latest();
        $registry = null === $latest ? $this->drafts->load()['registry'] : ($this->versions->snapshot($latest)['config']['registry'] ?? []);

        return array_replace(DefaultSettings::all()['registry'], (array) $registry);
    }
}
