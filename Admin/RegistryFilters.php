<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

final readonly class RegistryFilters
{
    public const PER_PAGE = 25;

    public function __construct(
        public ?string $action = null,
        public ?string $source = null,
        public ?int $version = null,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public ?string $search = null,
        public int $page = 1,
    ) {
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): self
    {
        $date = static function (mixed $value, string $time): ?\DateTimeImmutable {
            $value = \is_string($value) ? $value : '';

            return 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? new \DateTimeImmutable($value.' '.$time, new \DateTimeZone('UTC')) : null;
        };
        $text = static fn (mixed $value): ?string => \is_string($value) && '' !== trim($value) ? trim($value) : null;

        return new self(
            $text($query['action'] ?? null),
            $text($query['source'] ?? null),
            \is_string($query['version'] ?? null) && ctype_digit($query['version']) ? (int) $query['version'] : null,
            $date($query['from'] ?? null, '00:00:00'),
            $date($query['to'] ?? null, '23:59:59'),
            $text($query['q'] ?? null),
            max(1, (int) ($query['page'] ?? 1)),
        );
    }

    /** @return array<string, scalar> */
    public function toQuery(): array
    {
        return array_filter([
            'action' => $this->action,
            'source' => $this->source,
            'version' => $this->version,
            'from' => $this->from?->format('Y-m-d'),
            'to' => $this->to?->format('Y-m-d'),
            'q' => $this->search,
        ], static fn (mixed $value): bool => null !== $value);
    }
}
