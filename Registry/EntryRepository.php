<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

use CookieConsentManager\Model\ConsentEntry;
use CookieConsentManager\Model\ConsentEntryQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;

final readonly class EntryRepository
{
    public function exists(string $consentId): bool
    {
        return ConsentEntryQuery::create()->filterByConsentId($consentId)->count() > 0;
    }

    public function count(): int
    {
        return ConsentEntryQuery::create()->count();
    }

    public function existsOlderThan(\DateTimeInterface $limit): bool
    {
        return ConsentEntryQuery::create()->filterByRecordedAt($limit, Criteria::LESS_THAN)->count() > 0;
    }

    public function countOlderThan(\DateTimeInterface $cutoff): int
    {
        return ConsentEntryQuery::create()->filterByRecordedAt($cutoff, Criteria::LESS_THAN)->count();
    }

    /** Deletes by batches, so that a large registry never holds one long lock nor one huge transaction. */
    public function purgeOlderThan(\DateTimeInterface $cutoff, int $batchSize = 10000): int
    {
        $statement = Propel::getWriteConnection('TheliaMain')->prepare(
            \sprintf('DELETE FROM ccm_consent_entry WHERE recorded_at < :cutoff LIMIT %d', max(1, $batchSize)),
        );

        $deleted = 0;
        do {
            $statement->execute(['cutoff' => $cutoff->format('Y-m-d H:i:s')]);
            $batch = $statement->rowCount();
            $deleted += $batch;
        } while ($batch >= max(1, $batchSize));

        return $deleted;
    }

    /** @return list<ConsentEntry> */
    public function forConsentId(string $consentId): array
    {
        return array_values(iterator_to_array(
            ConsentEntryQuery::create()->filterByConsentId($consentId)->orderById()->find(),
            false,
        ));
    }

    /**
     * Entries in id order, by chunks so that a large registry never sits in memory.
     *
     * @return \Generator<int, ConsentEntry>
     */
    public function iterate(?string $action = null, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null, int $chunk = 500): \Generator
    {
        $lastId = 0;

        while (true) {
            $query = ConsentEntryQuery::create()->filterById($lastId, Criteria::GREATER_THAN)->orderById()->limit($chunk);
            if (null !== $action) {
                $query->filterByAction($action);
            }
            if (null !== $from) {
                $query->filterByRecordedAt($from, Criteria::GREATER_EQUAL);
            }
            if (null !== $to) {
                $query->filterByRecordedAt($to, Criteria::LESS_EQUAL);
            }

            $entries = $query->find();
            if (0 === \count($entries)) {
                return;
            }

            foreach ($entries as $entry) {
                $lastId = $entry->getId();
                yield $entry;
            }
        }
    }
}
