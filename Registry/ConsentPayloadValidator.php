<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

use CookieConsentManager\Publication\VersionRepository;

/** Request schema of CONTRATS.md section 2. */
final readonly class ConsentPayloadValidator
{
    public const ACTIONS = ['accept_all', 'reject_all', 'custom', 'service_allow', 'withdraw', 'close_reject', 'exempt_optout', 'renew'];
    public const SOURCES = ['banner', 'preferences', 'placeholder', 'api'];

    public function __construct(private VersionRepository $versions)
    {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws ConsentRejectedException 422
     */
    public function validate(array $data): ConsentPayload
    {
        if (1 !== ($data['format'] ?? null)) {
            throw $this->invalid('format');
        }

        $consentId = $data['consent_id'] ?? null;
        if (null !== $consentId && !Uuid4::isValid($consentId)) {
            throw $this->invalid('consent_id');
        }

        $action = $this->enum($data, 'action', self::ACTIONS);
        $source = $this->enum($data, 'source', self::SOURCES);

        $configVersion = $data['config_version'] ?? null;
        $version = \is_int($configVersion) ? $this->versions->find($configVersion) : null;
        if (null === $version) {
            throw $this->invalid('config_version');
        }

        $servedHash = $data['served_hash'] ?? null;
        if (!\is_string($servedHash) || 1 !== preg_match('/^[0-9a-f]{12}$/', $servedHash)) {
            throw $this->invalid('served_hash');
        }

        $locale = $data['locale'] ?? null;
        if (!\is_string($locale) || 1 !== preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $locale)) {
            throw $this->invalid('locale');
        }

        $path = $data['path'] ?? null;
        if (!\is_string($path) || '' === $path || \strlen($path) > 255 || !str_starts_with($path, '/') || false !== strpbrk($path, '?#')) {
            throw $this->invalid('path');
        }

        $snapshot = $this->versions->snapshot($version);
        $exemptKeys = array_column($snapshot['config']['exempt_measurement'], 'service');
        $serviceKeys = array_values(array_diff(array_column($snapshot['services_resolved'], 'key'), $exemptKeys));
        $purposeCodes = array_values(array_diff(array_unique(array_column($snapshot['services_resolved'], 'purpose')), ['essential']));

        return new ConsentPayload(
            $consentId,
            $action,
            $source,
            $configVersion,
            $servedHash,
            $locale,
            $path,
            $this->states($data, 'purposes', $purposeCodes, [0, 1, 2], true),
            $this->states($data, 'services', $serviceKeys, [0, 1], true),
            $this->states($data, 'exempt', $exemptKeys, [0, 1], false),
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $known
     * @param list<int>            $allowed
     *
     * @return array<string, int>
     */
    private function states(array $data, string $field, array $known, array $allowed, bool $required): array
    {
        if (!\array_key_exists($field, $data)) {
            if ($required) {
                throw $this->invalid($field);
            }

            return [];
        }

        $map = $data[$field];
        if (!\is_array($map) || (\count($map) > 0 && array_is_list($map))) {
            throw $this->invalid($field);
        }

        $states = [];
        foreach ($map as $key => $value) {
            if (!\in_array((string) $key, $known, true) || !\is_int($value) || !\in_array($value, $allowed, true)) {
                throw $this->invalid($field.'.'.$key);
            }
            $states[(string) $key] = $value;
        }

        return $states;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $values
     */
    private function enum(array $data, string $field, array $values): string
    {
        $value = $data[$field] ?? null;
        if (!\is_string($value) || !\in_array($value, $values, true)) {
            throw $this->invalid($field);
        }

        return $value;
    }

    private function invalid(string $field): ConsentRejectedException
    {
        return new ConsentRejectedException(422, 'invalid_payload', $field);
    }
}
