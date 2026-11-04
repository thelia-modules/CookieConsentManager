<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Publication\PublishedConfigBuilder;
use CookieConsentManager\Publication\PublishedVersionPointer;
use CookieConsentManager\Publication\SnapshotAssembler;
use CookieConsentManager\Publication\VersionRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Lang;

/**
 * Decides what a page shows: nothing while no version is published, the latest published version
 * otherwise, the draft for a request carrying the preview token while an administrator is connected, the published version without
 * reading or writing a choice for a request carrying the same token with `ccm_audit`.
 */
final class FrontConfigProvider implements ResetInterface
{
    public const AUDIT_QUERY = 'ccm_audit';

    /** @var array<string, FrontConfig|null> */
    private array $memo = [];

    public function __construct(
        private readonly VersionRepository $versions,
        private readonly PublishedConfigBuilder $builder,
        private readonly SnapshotAssembler $assembler,
        private readonly DraftRepository $drafts,
        private readonly PreviewToken $tokens,
        private readonly ServedHashResolver $hashes,
        private readonly SecurityContext $security,
        private readonly PublishedVersionPointer $pointer,
        #[Autowire(service: 'cache.app')]
        private readonly CacheInterface $cache,
        private readonly FrontUrls $urls,
    ) {
    }

    public function forRequest(Request $request): ?FrontConfig
    {
        $locale = $this->locale($request);
        $preview = $this->token($request, PreviewToken::QUERY);
        if (null !== $preview && !$this->security->hasAdminUser()) {
            $preview = null;
        }
        $audit = $this->token($request, self::AUDIT_QUERY);
        $memoKey = $locale.'|'.($preview ?? '').'|'.($audit ?? '');

        if (!\array_key_exists($memoKey, $this->memo)) {
            $resolved = $this->resolve($locale, $preview, $audit);
            $this->memo[$memoKey] = null === $resolved ? null : $this->withUrls($resolved, $preview);
        }

        if ('preview' === $this->memo[$memoKey]?->mode()) {
            $request->attributes->set(PreviewToken::PROOF_ATTRIBUTE, $preview);
        }

        return $this->memo[$memoKey];
    }

    /** Clears what was memoised for the request (called by the kernel between the requests of a worker). */
    public function reset(): void
    {
        $this->memo = [];
    }

    /** The published configuration for a language, whatever the request: what a page view shows once nothing is previewed or audited. */
    public function forLocale(string $locale): ?FrontConfig
    {
        $number = $this->pointer->latest();

        $published = 0 === $number ? null : $this->published($number, $locale);

        return null === $published ? null : $this->withUrls($published, null);
    }

    /** The published configuration of one version for a language, as the page that embedded it saw it. */
    public function forVersion(int $number, string $locale): ?FrontConfig
    {
        $published = $this->published($number, $locale);

        return null === $published ? null : $this->withUrls($published, null);
    }

    // The cached configuration holds root paths; the ones of this request come from the router, so a shop in a sub-directory gets its base path.
    private function withUrls(FrontConfig $front, ?string $preview): FrontConfig
    {
        $script = 'preview' === $front->mode() && null !== $preview
            ? $this->urls->previewScript($preview)
            : $this->urls->script($front->config['version'], $front->config['hash']);

        $urls = ['endpoint' => $this->urls->endpoint(), 'script' => $script, 'preferencesUrl' => $this->urls->preferences()];
        // The preferences panel is fetched when the visitor opens it. The draft preview inlines its own: the panel of the published version would not show it.
        if ('preview' !== $front->mode()) {
            $urls['panel'] = $this->urls->panel($front->config['version'], $front->config['hash'], $front->config['locale']);
        }

        return new FrontConfig(array_replace($front->config, $urls), $front->snapshot);
    }

    private function resolve(string $locale, ?string $preview, ?string $audit): ?FrontConfig
    {
        $number = $this->pointer->latest();

        if (null !== $preview) {
            $draft = $this->drafts->load();
            if ($this->tokens->isValid($preview, $draft)) {
                $snapshot = $this->assembler->assemble($draft);

                return new FrontConfig(
                    $this->builder->build($snapshot, $locale, $number, substr($preview, 0, 12), 0, 'preview'),
                    $snapshot,
                );
            }
        }

        $published = 0 === $number ? null : $this->published($number, $locale);
        if (null === $published) {
            return null;
        }

        if (null !== $audit && $this->tokens->isValid($audit, $this->drafts->load())) {
            return new FrontConfig(array_replace($published->config, ['mode' => 'audit']), $published->snapshot);
        }

        return $published;
    }

    // Built once per (version, language, module version and overrides) and kept in the application cache: a page view reads
    // neither the snapshot nor the script files again.
    private function published(int $number, string $locale): ?FrontConfig
    {
        $key = \sprintf('ccm.front.%d.%s.%s', $number, preg_replace('/\W/', '_', $locale), $this->hashes->stamp());

        return $this->cache->get($key, function (ItemInterface $item) use ($number, $locale): ?FrontConfig {
            $latest = $this->versions->find($number);
            if (null === $latest) {
                $item->expiresAfter(5);

                return null;
            }

            $snapshot = $this->versions->snapshot($latest);

            return new FrontConfig(
                $this->builder->build(
                    $snapshot,
                    $locale,
                    $latest->getVersion(),
                    $this->hashes->forVersion($latest),
                    $this->versions->substantialVersionUpTo($latest->getVersion()),
                ),
                $snapshot,
            );
        });
    }

    private function token(Request $request, string $name): ?string
    {
        $value = $request->query->get($name);

        return \is_string($value) && '' !== $value ? $value : null;
    }

    public function locale(Request $request): string
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        if (null !== $session && method_exists($session, 'getLang') && $session->getLang() instanceof Lang) {
            return $session->getLang()->getLocale();
        }

        return Lang::getDefaultLanguage()->getLocale();
    }
}
