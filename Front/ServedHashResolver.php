<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use CookieConsentManager\Model\Version;
use CookieConsentManager\Publication\ModuleVersion;
use CookieConsentManager\Publication\OverriddenTemplatesProvider;
use CookieConsentManager\Publication\Publisher;
use CookieConsentManager\Publication\ServedScriptHasher;
use CookieConsentManager\Publication\VersionRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Hash the visitor is served right now. The one stored at publication freezes the module and the
 * theme of that day: after an update of the module or of a theme template the script changes, so
 * its immutable URL must change too, hence the recomputation from the published snapshot.
 *
 * The recomputation (script files read and hashed, templates of the theme scanned) is kept in the
 * application cache under a key made of the version and of a cheap stamp of what can change the
 * result without a publication: the version of the module and the fingerprint of the overrides.
 */
final class ServedHashResolver implements ResetInterface
{
    /** @var array<string, string> */
    private array $memo = [];
    private ?string $stamp = null;

    public function __construct(
        private readonly VersionRepository $versions,
        private readonly ServedScriptHasher $scriptHasher,
        private readonly ModuleVersion $moduleVersion,
        private readonly OverriddenTemplatesProvider $templates,
        #[Autowire(service: 'cache.app')]
        private readonly CacheInterface $cache,
    ) {
    }

    /** What, besides a publication, changes the served script: the module and the theme overrides. */
    public function stamp(): string
    {
        return $this->stamp ??= substr(hash('sha256', $this->moduleVersion->current()."\n".$this->templates->signature()), 0, 16);
    }

    public function forVersion(Version $version): string
    {
        $key = \sprintf('ccm.hash.%d.%s.%s', $version->getVersion(), substr($version->getConfigHash(), 0, 16), $this->stamp());

        return $this->memo[$key] ??= $this->cache->get($key, fn (): string => Publisher::servedHash(
            $version->getConfigHash(),
            $this->moduleVersion->current(),
            $this->scriptHasher->hash($this->versions->snapshot($version)),
            $this->templates->list(),
        ));
    }

    public function reset(): void
    {
        $this->memo = [];
        $this->stamp = null;
    }
}
