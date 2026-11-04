<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

use CookieConsentManager\Draft\DraftRepository;

final readonly class VersionRestorer
{
    public function __construct(
        private VersionRepository $versions,
        private DraftRepository $drafts,
    ) {
    }

    public function restore(int $number, ?string $author): void
    {
        $version = $this->versions->find($number) ?? throw new \InvalidArgumentException(\sprintf('Version %d does not exist.', $number));

        $this->drafts->replace($this->versions->snapshot($version)['config'], $author, $number);
    }
}
