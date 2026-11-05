<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use CookieConsentManager\Publication\CanonicalJson;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Token of the preview of the draft: derived from the content of the draft and the secret of the
 * kernel, so that it needs no storage, expires with the next edit and cannot be guessed.
 */
final readonly class PreviewToken
{
    public const PATTERN = '[0-9a-f]{32}';
    public const QUERY = 'ccm_preview';
    // Set by the server on a page opened by a connected administrator, read by the stateless script route.
    public const PROOF_COOKIE = 'ccm_preview_proof';
    public const PROOF_ATTRIBUTE = '_ccm_preview_token';
    public const MINIMUM_SECRET_BYTES = 32;

    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        private string $secret,
    ) {
    }

    /** An empty or short secret makes every token computable by anyone: the preview is then unavailable, never weakened. */
    public function isAvailable(): bool
    {
        return \strlen($this->secret) >= self::MINIMUM_SECRET_BYTES;
    }

    /**
     * @param array<string, mixed> $draft normalized draft
     *
     * @return ?string null while the preview is unavailable
     */
    public function forDraft(array $draft): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }

        return substr(hash_hmac('sha256', CanonicalJson::encode($draft), $this->secret), 0, 32);
    }

    /** @param array<string, mixed> $draft normalized draft */
    public function isValid(string $token, array $draft): bool
    {
        $expected = $this->forDraft($draft);

        return null !== $expected && 1 === preg_match('/^'.self::PATTERN.'$/D', $token) && hash_equals($expected, $token);
    }

    /** Proof that the token was presented on a page opened by an administrator: bound to the token, valid until the next edit of the draft. */
    public function proofFor(string $token): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }

        return substr(hash_hmac('sha256', 'ccm-preview-proof|'.$token, $this->secret), 0, 32);
    }

    public function proofIsValid(string $token, mixed $proof): bool
    {
        $expected = $this->proofFor($token);

        return null !== $expected && \is_string($proof) && hash_equals($expected, $proof);
    }
}
