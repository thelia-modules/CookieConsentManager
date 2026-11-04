<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Publication\PublishedConfigBuilder;
use CookieConsentManager\Publication\SnapshotAssembler;
use CookieConsentManager\Publication\VersionRepository;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Lang;

/**
 * Decides what a page shows: nothing while no version is published, the latest published version
 * otherwise, the draft for a request carrying the preview token while an administrator is connected, the published version without
 * reading or writing a choice for a request carrying the same token with `ccm_audit`.
 */
final class FrontConfigProvider
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
            $this->memo[$memoKey] = $this->resolve($locale, $preview, $audit);
        }

        if ('preview' === $this->memo[$memoKey]?->mode()) {
            $request->attributes->set(PreviewToken::PROOF_ATTRIBUTE, $preview);
        }

        return $this->memo[$memoKey];
    }

    private function resolve(string $locale, ?string $preview, ?string $audit): ?FrontConfig
    {
        $latest = $this->versions->latest();

        if (null !== $preview) {
            $draft = $this->drafts->load();
            if ($this->tokens->isValid($preview, $draft)) {
                $snapshot = $this->assembler->assemble($draft);

                return new FrontConfig(
                    $this->builder->build($snapshot, $locale, $latest?->getVersion() ?? 0, substr($preview, 0, 12), 0, 'preview', \sprintf('/ccm/preview-%s.js', $preview)),
                    $snapshot,
                );
            }
        }

        if (null === $latest) {
            return null;
        }

        $snapshot = $this->versions->snapshot($latest);
        $mode = null !== $audit && $this->tokens->isValid($audit, $this->drafts->load()) ? 'audit' : 'live';

        return new FrontConfig(
            $this->builder->build(
                $snapshot,
                $locale,
                $latest->getVersion(),
                $this->hashes->forVersion($latest),
                $this->versions->substantialVersionUpTo($latest->getVersion()),
                $mode,
            ),
            $snapshot,
        );
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
