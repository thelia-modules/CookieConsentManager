<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Model\ConsentEntry;
use CookieConsentManager\Model\ConsentEntryQuery;
use CookieConsentManager\Model\Map\ConsentEntryTableMap;
use CookieConsentManager\Registry\LastPurgeStore;
use CookieConsentManager\Registry\RegistryCsvExporter;
use CookieConsentManager\Registry\RegistryOptions;
use CookieConsentManager\Registry\RegistryPurger;
use CookieConsentManager\Tests\Support\CcmTestCase;
use Propel\Runtime\Propel;

final class RegistryPurgeTest extends CcmTestCase
{
    private const NOW = '2026-10-31 12:00:00';

    public function testPurgesWhatIsOlderThanTheRetentionAndRemembersWhen(): void
    {
        $draft = $this->ccm->drafts->load();
        $draft['registry']['retention_months'] = 60;
        $this->ccm->drafts->replace($draft, 'alice');

        $this->entry('old-1', '2021-10-30 11:59:59');
        $this->entry('old-2', '2020-01-01 00:00:00');
        $this->entry('kept-1', '2021-11-02 00:00:00');
        $this->entry('kept-2', '2026-10-30 00:00:00');

        $store = new class extends LastPurgeStore {
            public ?\DateTimeImmutable $written = null;

            public function read(): ?\DateTimeImmutable
            {
                return $this->written;
            }

            public function write(\DateTimeImmutable $at): void
            {
                $this->written = $at;
            }
        };
        $purger = new RegistryPurger(new RegistryOptions($this->ccm->drafts, $this->ccm->versions), $this->ccm->entries, $store);
        $now = new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC'));

        self::assertTrue($purger->isLate($now), 'never purged');
        self::assertSame(2, $purger->purge($now, true), 'a dry run counts');
        self::assertSame(4, ConsentEntryQuery::create()->filterByConsentId(['old-1', 'old-2', 'kept-1', 'kept-2'])->count());

        self::assertSame(2, $purger->purge($now));

        self::assertSame(['kept-1', 'kept-2'], $this->ids());
        self::assertFalse($purger->isLate($now));
        self::assertFalse($purger->isLate($now->modify('+2 days')), 'a recent purge is never late');
        self::assertTrue($purger->isLate($now->modify('+8 days')), 'eight days later, kept-1 is past the retention and nobody purged');
    }

    public function testANeverPurgedRegistryIsLateOnlyOnceItHoldsEntriesPastTheRetention(): void
    {
        $store = new class extends LastPurgeStore {
            public function read(): ?\DateTimeImmutable
            {
                return null;
            }
        };
        $purger = new RegistryPurger(new RegistryOptions($this->ccm->drafts, $this->ccm->versions), $this->ccm->entries, $store);
        $now = new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC'));
        ConsentEntryQuery::create()->deleteAll();

        self::assertFalse($purger->isLate($now), 'an empty registry has nothing to purge');

        $this->entry('fresh-1', '2026-10-28 00:00:00');
        self::assertFalse($purger->isLate($now), 'a registry of the last days is not late');

        $this->entry('week-old', '2026-10-24 11:00:00');
        self::assertFalse($purger->isLate($now), 'an entry older than 7 days but within the retention is not waiting for a purge');

        $this->entry('expired', '2020-01-01 00:00:00');
        self::assertTrue($purger->isLate($now), 'an entry past the retention is waiting for a purge');
    }

    public function testTheCsvHasTheColumnsOfAnnexBInOrderAndNeutralisesFormulas(): void
    {
        $this->entry('csv-1', '2026-10-30 10:00:00', '/p', '=1+1');

        $stream = fopen('php://memory', 'w+');
        $rows = (new RegistryCsvExporter(new RegistryOptions($this->ccm->drafts, $this->ccm->versions), $this->ccm->entries))->write($stream);
        rewind($stream);
        $lines = array_map(static fn (string $line): array => str_getcsv($line, ';', '"', '\\'), array_filter(explode("\n", (string) stream_get_contents($stream))));

        self::assertGreaterThanOrEqual(1, $rows);
        self::assertSame(['consent_id', 'recorded_at_utc', 'action', 'source', 'version', 'version_hash', 'locale', 'path', 'browser', 'purposes_json', 'services_json'], $lines[0]);
        $row = array_values(array_filter($lines, static fn (array $line): bool => 'csv-1' === $line[0]))[0];
        self::assertSame('2026-10-30T10:00:00Z', $row[1]);
        self::assertSame("'=1+1", $row[8], 'a cell that a spreadsheet would run as a formula is neutralised');

        $draft = $this->ccm->drafts->load();
        $draft['registry']['ip_mode'] = 'truncated';
        $this->ccm->drafts->replace($draft, 'alice');
        $stream = fopen('php://memory', 'w+');
        (new RegistryCsvExporter(new RegistryOptions($this->ccm->drafts, $this->ccm->versions), $this->ccm->entries))->write($stream);
        rewind($stream);
        self::assertStringEndsWith('services_json;ip_truncated', (string) strtok((string) stream_get_contents($stream), "\n"));
    }

    public function testTheBatchedPurgeRemovesEverythingExpiredAndNothingRecentInSeveralBatches(): void
    {
        $this->entry('old-1', '2020-01-01 00:00:00');
        $this->entry('old-2', '2020-01-02 00:00:00');
        $this->entry('old-3', '2020-01-03 00:00:00');
        $this->entry('old-4', '2020-01-04 00:00:00');
        $this->entry('old-5', '2020-01-05 00:00:00');
        $this->entry('kept-1', '2021-11-02 00:00:00');
        $this->entry('kept-2', '2026-10-30 00:00:00');
        $cutoff = new \DateTimeImmutable('2021-10-31 12:00:00', new \DateTimeZone('UTC'));
        $before = $this->deleteStatements();

        self::assertSame(5, $this->ccm->entries->purgeOlderThan($cutoff, 2));

        self::assertSame(3, $this->deleteStatements() - $before, 'five rows by batches of two: 2, 2, 1');
        self::assertSame(['kept-1', 'kept-2'], $this->ids());
        self::assertSame(0, $this->ccm->entries->purgeOlderThan($cutoff, 2), 'nothing left to purge');
    }

    public function testADryRunCountsWithoutLoadingAnyRow(): void
    {
        $this->entry('old-1', '2020-01-01 00:00:00');
        $this->entry('old-2', '2020-01-02 00:00:00');
        $this->entry('kept-1', '2026-10-30 00:00:00');
        $purger = new RegistryPurger(new RegistryOptions($this->ccm->drafts, $this->ccm->versions), $this->ccm->entries, new LastPurgeStore());
        // The test case turns pooling off; a loaded row is visible only with it on
        Propel::enableInstancePooling();
        ConsentEntryTableMap::clearInstancePool();

        $counted = $purger->purge(new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC')), true);

        self::assertGreaterThanOrEqual(2, $counted);
        self::assertSame([], ConsentEntryTableMap::$instances, 'a loaded row would sit in the instance pool');
    }

    public function testTheExportKeepsNoRowInMemoryAndRestoresInstancePooling(): void
    {
        ConsentEntryQuery::create()->deleteAll();
        $this->entry('exp-1', '2026-10-30 10:00:00');
        $this->entry('exp-2', '2026-10-30 11:00:00', '/q');
        $exporter = new RegistryCsvExporter(new RegistryOptions($this->ccm->drafts, $this->ccm->versions), $this->ccm->entries);

        // The test case turns pooling off; a hydrated row is kept in memory only with it on
        Propel::enableInstancePooling();
        ConsentEntryTableMap::clearInstancePool();
        $stream = fopen('php://memory', 'w+');
        self::assertSame(2, $exporter->write($stream));

        self::assertTrue(Propel::isInstancePoolingEnabled(), 'the previous state is restored');
        self::assertSame([], ConsentEntryTableMap::$instances, 'no exported row stays in the pool');
        rewind($stream);
        $lines = explode("\n", trim((string) stream_get_contents($stream)));
        self::assertCount(3, $lines);
        self::assertSame(['exp-1', '2026-10-30T10:00:00Z', 'custom', 'banner', '1', 'abcdef012345', 'fr_FR', '/p', '', '{}', '{}'], str_getcsv($lines[1], ';', '"', '\\'));
        self::assertSame('/q', str_getcsv($lines[2], ';', '"', '\\')[7]);

        Propel::disableInstancePooling();
        $exporter->write(fopen('php://memory', 'w+'));
        self::assertFalse(Propel::isInstancePoolingEnabled(), 'a pool that was off stays off');
    }

    private function deleteStatements(): int
    {
        return (int) Propel::getWriteConnection('TheliaMain')->query("SHOW SESSION STATUS LIKE 'Com_delete'")->fetch(\PDO::FETCH_NUM)[1];
    }

    /** @return list<string> */
    private function ids(): array
    {
        return array_values(array_map(
            static fn (ConsentEntry $entry): string => $entry->getConsentId(),
            iterator_to_array(ConsentEntryQuery::create()->filterByConsentId(['old-1', 'old-2', 'kept-1', 'kept-2'])->orderById()->find(), false),
        ));
    }

    private function entry(string $id, string $at, string $path = '/p', ?string $browser = null): void
    {
        (new ConsentEntry())
            ->setConsentId($id)
            ->setRecordedAt(new \DateTimeImmutable($at, new \DateTimeZone('UTC')))
            ->setAction('custom')->setSource('banner')->setVersion(1)->setVersionHash('abcdef012345')
            ->setLocale('fr_FR')->setPath($path)->setBrowser($browser)->setPurposesJson('{}')->setServicesJson('{}')
            ->save();
    }
}
