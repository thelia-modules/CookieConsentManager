<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\CookieConsentManager;
use CookieConsentManager\Install\DataEraser;
use CookieConsentManager\Install\SchemaInstaller;
use CookieConsentManager\Registry\LastPurgeStore;
use CookieConsentManager\Tests\Support\CcmTestCase;
use Propel\Runtime\Propel;
use Thelia\Model\ConfigQuery;

/**
 * A DROP TABLE commits: no test transaction. The tables are created again by the install script
 * in tearDown(), empty, which is what every other test of the module starts from.
 */
final class UninstallTest extends CcmTestCase
{
    protected bool $useTransaction = false;

    protected function tearDown(): void
    {
        $installer = SchemaInstaller::forModule(\dirname(__DIR__, 3));
        $installer->apply();
        $installer->applyUpdates('0.0.0');
        ConfigQuery::create()->filterByName(LastPurgeStore::KEY)->delete();
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testUninstallingKeepsTheDataUnlessTheDeletionIsAsked(): void
    {
        (new LastPurgeStore())->write(new \DateTimeImmutable('2026-10-31 03:00:00'));

        (new CookieConsentManager())->destroy(null, false);

        self::assertSame(DataEraser::TABLES, $this->existingTables(DataEraser::TABLES), 'the tables stay');
        self::assertSame(1, ConfigQuery::create()->filterByName(LastPurgeStore::KEY)->count(), 'the purge date stays');
    }

    public function testUninstallingWithTheDeletionAskedDropsTheTablesAndTheConfigurationKey(): void
    {
        (new LastPurgeStore())->write(new \DateTimeImmutable('2026-10-31 03:00:00'));
        self::assertSame(DataEraser::TABLES, $this->existingTables(DataEraser::TABLES));

        (new CookieConsentManager())->destroy(null, true);

        self::assertSame([], $this->existingTables(DataEraser::TABLES));
        self::assertSame(0, ConfigQuery::create()->filterByName(LastPurgeStore::KEY)->count());
    }

    public function testTheErasedTablesAreExactlyTheOnesTheInstallScriptCreates(): void
    {
        preg_match_all('/CREATE TABLE IF NOT EXISTS `(\w+)`/', (string) file_get_contents(\dirname(__DIR__, 3).'/Config/TheliaMain.sql'), $matches);

        $created = $matches[1];
        $erased = DataEraser::TABLES;
        sort($created);
        sort($erased);
        self::assertSame($created, $erased);
    }

    /**
     * @param list<string> $tables
     *
     * @return list<string>
     */
    private function existingTables(array $tables): array
    {
        $existing = array_map(
            static fn (string $table): ?string => false === Propel::getReadConnection('TheliaMain')->query(\sprintf("SHOW TABLES LIKE '%s'", $table))->fetch() ? null : $table,
            $tables,
        );

        return array_values(array_filter($existing));
    }
}
