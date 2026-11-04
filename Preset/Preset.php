<?php

declare(strict_types=1);

namespace CookieConsentManager\Preset;

/** A country preset as shipped in Resources/presets: a partial ccm-config/1 document and its `preset` identity block. */
final readonly class Preset
{
    /** Keys of the document a preset may carry; services, colors and addresses depend on the shop and are never preset. */
    public const SECTIONS = ['schema', 'preset', 'general', 'policy', 'texts', 'purposes', 'consent_mode', 'registry', 'exempt_measurement'];

    /**
     * @param list<string>         $legalBasis
     * @param array<string, mixed> $document   the document without its `preset` block
     */
    public function __construct(
        public string $code,
        public string $country,
        public string $label,
        public array $legalBasis,
        public array $document,
    ) {
    }

    /**
     * @param array<string, mixed> $document
     *
     * @throws \InvalidArgumentException
     */
    public static function fromDocument(array $document): self
    {
        $identity = $document['preset'] ?? null;
        if (!\is_array($identity) || 1 !== preg_match('/^[a-z]{2}-[A-Z]{2}$/', (string) ($identity['code'] ?? ''))) {
            throw new \InvalidArgumentException('A preset needs a "preset.code" such as "fr-FR".');
        }
        $unknown = array_diff(array_keys($document), self::SECTIONS);
        if ([] !== $unknown) {
            throw new \InvalidArgumentException('Preset '.$identity['code'].': unknown section(s) '.implode(', ', $unknown));
        }
        if (isset($document['policy']) && [] !== array_diff(array_keys((array) $document['policy']), ['page_title'])) {
            throw new \InvalidArgumentException('Preset '.$identity['code'].': only policy.page_title may be preset (addresses depend on the shop).');
        }
        unset($document['preset']);

        return new self(
            (string) $identity['code'],
            (string) ($identity['country'] ?? substr((string) $identity['code'], 3)),
            (string) ($identity['label'] ?? $identity['code']),
            array_values(array_map('strval', (array) ($identity['legal_basis'] ?? []))),
            $document,
        );
    }

    /** Language of the texts the preset provides: `fr` for `fr-FR`. */
    public function language(): string
    {
        return strtolower(substr($this->code, 0, 2));
    }
}
