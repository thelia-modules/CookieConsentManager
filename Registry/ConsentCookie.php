<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

use Symfony\Component\HttpFoundation\Cookie;

/** `ccm_consent`: CONTRATS.md section 1. */
final class ConsentCookie extends Cookie
{
    public const NAME = 'ccm_consent';
    public const MAX_AGE = 34214400;
    public const MAX_ENCODED_BYTES = 3500;
    public const FORMAT = 1;

    private function __construct(string $value, bool $secure)
    {
        parent::__construct(self::NAME, $value, time() + self::MAX_AGE, '/', null, $secure, false, false, self::SAMESITE_LAX);
    }

    /**
     * @param array<string, int> $purposes
     * @param array<string, int> $services
     * @param array<string, int> $exempt
     */
    public static function forChoice(string $consentId, int $version, int $timestamp, array $purposes, array $services, array $exempt, bool $secure): self
    {
        return new self(self::encode([
            'f' => self::FORMAT,
            'id' => $consentId,
            'ver' => $version,
            't' => $timestamp,
            'p' => (object) $purposes,
            's' => (object) $services,
            'x' => (object) $exempt,
            'pd' => 0,
        ]), $secure);
    }

    /** @param array<string, mixed> $payload */
    public static function encode(array $payload): string
    {
        return rtrim(strtr(base64_encode(json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    }

    /** @return array<string, mixed>|null null when absent, malformed or of an unknown format */
    public static function decode(?string $value): ?array
    {
        if (null === $value || '' === $value || \strlen($value) > self::MAX_ENCODED_BYTES) {
            return null;
        }

        $json = base64_decode(strtr($value, '-_', '+/'), true);
        $payload = false === $json ? null : json_decode($json, true);

        if (!\is_array($payload) || self::FORMAT !== ($payload['f'] ?? null) || !Uuid4::isValid($payload['id'] ?? null)) {
            return null;
        }

        return $payload;
    }

    /**
     * Same rule as head.js and engine.js: a choice is no consent once it is older than the renewal
     * or older than the last substantial version.
     *
     * @param array<string, mixed> $state
     */
    public static function isStale(array $state, int $repromptDays, int $substantialVersion, int $now): bool
    {
        return (int) ($state['t'] ?? 0) + $repromptDays * 86400 <= $now
            || ($substantialVersion > 0 && (int) ($state['ver'] ?? 0) < $substantialVersion);
    }

    public function encodedLength(): int
    {
        return \strlen((string) $this->getValue());
    }

    // The Max-Age of the contract is a constant: the parent derives it from the expiry and
    // would print 34214399 as soon as a second has elapsed.
    public function __toString(): string
    {
        return \sprintf('%s=%s; Path=/; Max-Age=%d; SameSite=Lax%s', self::NAME, $this->getValue(), self::MAX_AGE, $this->isSecure() ? '; Secure' : '');
    }
}
