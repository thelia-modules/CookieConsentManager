<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Model\ConsentEntryQuery;
use Propel\Runtime\ActiveQuery\Criteria;

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

            return 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) ? new \DateTimeImmutable($value.' '.$time, new \DateTimeZone('UTC')) : null;
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

    /** The list and its CSV export read the registry through this one method, so that they always show the same entries. */
    public function applyTo(ConsentEntryQuery $query): ConsentEntryQuery
    {
        if (null !== $this->action) {
            $query->filterByAction($this->action);
        }
        if (null !== $this->source) {
            $query->filterBySource($this->source);
        }
        if (null !== $this->version) {
            $query->filterByVersion($this->version);
        }
        if (null !== $this->from) {
            $query->filterByRecordedAt($this->from, Criteria::GREATER_EQUAL);
        }
        if (null !== $this->to) {
            $query->filterByRecordedAt($this->to, Criteria::LESS_EQUAL);
        }
        if (null !== $this->search) {
            // An identifier is looked up by its start: the visitor reads it from the preferences panel.
            $query->filterByConsentId(addcslashes(strtolower($this->search), '%_\\').'%', Criteria::LIKE);
        }

        return $query;
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
