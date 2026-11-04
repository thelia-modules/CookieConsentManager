<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use CookieConsentManager\Model\Version;
use CookieConsentManager\Publication\ModuleVersion;
use CookieConsentManager\Publication\OverriddenTemplatesProvider;
use CookieConsentManager\Publication\Publisher;
use CookieConsentManager\Publication\ServedScriptHasher;
use CookieConsentManager\Publication\VersionRepository;

/**
 * Hash the visitor is served right now. The one stored at publication freezes the module and the
 * theme of that day: after an update of the module or of a theme template the script changes, so
 * its immutable URL must change too, hence the recomputation from the published snapshot.
 */
final class ServedHashResolver
{
    /** @var array<int, string> */
    private array $memo = [];

    public function __construct(
        private readonly VersionRepository $versions,
        private readonly ServedScriptHasher $scriptHasher,
        private readonly ModuleVersion $moduleVersion,
        private readonly OverriddenTemplatesProvider $templates,
    ) {
    }

    public function forVersion(Version $version): string
    {
        return $this->memo[$version->getVersion()] ??= Publisher::servedHash(
            $version->getConfigHash(),
            $this->moduleVersion->current(),
            $this->scriptHasher->hash($this->versions->snapshot($version)),
            $this->templates->list(),
        );
    }
}
