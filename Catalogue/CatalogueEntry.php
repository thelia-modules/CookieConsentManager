<?php

declare(strict_types=1);

namespace CookieConsentManager\Catalogue;

final readonly class CatalogueEntry
{
    /**
     * @param list<array{name: string, required: bool, label: array<string, string>, help: array<string, string>, pattern: ?string}> $params
     * @param list<string>                                                                                                           $cookies
     * @param list<string>                                                                                                           $storage
     * @param list<string>                                                                                                           $domains
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $purpose,
        public bool $verified,
        public string $kind,
        public bool $googleTag,
        public string $uri,
        public array $params,
        public array $cookies,
        public array $storage,
        public array $domains,
        public ?string $excluded,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        $params = [];
        foreach ((array) ($row['params'] ?? []) as $param) {
            $params[] = [
                'name' => (string) $param['name'],
                'required' => (bool) ($param['required'] ?? false),
                'label' => (array) ($param['label'] ?? []),
                'help' => (array) ($param['help'] ?? []),
                'pattern' => isset($param['pattern']) && '' !== $param['pattern'] ? (string) $param['pattern'] : null,
            ];
        }

        return new self(
            (string) $row['key'],
            (string) ($row['name'] ?? $row['key']),
            (string) $row['purpose'],
            (bool) ($row['verified'] ?? false),
            (string) ($row['kind'] ?? 'script'),
            (bool) ($row['googleTag'] ?? false),
            (string) ($row['uri'] ?? ''),
            $params,
            array_values(array_map('strval', (array) ($row['cookies'] ?? []))),
            array_values(array_map('strval', (array) ($row['storage'] ?? []))),
            array_values(array_map('strval', (array) ($row['domains'] ?? []))),
            isset($row['excluded']) ? (string) $row['excluded'] : null,
        );
    }
}
