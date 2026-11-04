<?php

declare(strict_types=1);

namespace CookieConsentManager\Catalogue;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CatalogueReader
{
    /** @var array<string, CatalogueEntry>|null */
    private ?array $entries = null;

    public function __construct(
        #[Autowire(value: __DIR__.'/../Resources/catalogue/catalogue.json')]
        private readonly string $cataloguePath,
    ) {
    }

    public function find(string $key): ?CatalogueEntry
    {
        return $this->all()[$key] ?? null;
    }

    /** @return array<string, CatalogueEntry> */
    public function all(): array
    {
        if (null !== $this->entries) {
            return $this->entries;
        }

        $content = is_file($this->cataloguePath) ? file_get_contents($this->cataloguePath) : false;
        $rows = false === $content ? [] : json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        $this->entries = [];
        foreach ($rows as $row) {
            $entry = CatalogueEntry::fromArray($row);
            $this->entries[$entry->key] = $entry;
        }

        return $this->entries;
    }

    /** @return array<string, CatalogueEntry> */
    public function exposed(bool $experimental): array
    {
        return array_filter(
            $this->all(),
            static fn (CatalogueEntry $entry): bool => null === $entry->excluded && ($experimental || $entry->verified),
        );
    }
}
