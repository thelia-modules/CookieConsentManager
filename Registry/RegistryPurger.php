<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

final readonly class RegistryPurger
{
    public function __construct(
        private RegistryOptions $options,
        private EntryRepository $entries,
        private LastPurgeStore $lastPurge,
    ) {
    }

    public function cutoff(?\DateTimeImmutable $now = null): \DateTimeImmutable
    {
        $months = $this->options->current()['retention_months'];

        return ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify(\sprintf('-%d months', $months));
    }

    /** @return int number of entries deleted */
    public function purge(?\DateTimeImmutable $now = null, bool $dryRun = false): int
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $cutoff = $this->cutoff($now);

        if ($dryRun) {
            return $this->entries->countOlderThan($cutoff);
        }

        $deleted = $this->entries->purgeOlderThan($cutoff);
        $this->lastPurge->write($now);

        return $deleted;
    }

    /**
     * True when entries wait past the retention and no purge ran for more than seven days (the dashboard
     * alert, SPEC 6.4): a registry with nothing to purge is never late.
     */
    public function isLate(?\DateTimeImmutable $now = null): bool
    {
        $last = $this->lastPurge->read();
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $limit = $now->modify('-7 days');

        return (null === $last || $last < $limit) && $this->entries->existsOlderThan($this->cutoff($now));
    }
}
