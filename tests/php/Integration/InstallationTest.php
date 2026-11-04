<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Install\SchemaInstaller;
use CookieConsentManager\Model\ConsentEntry;
use CookieConsentManager\Model\ConsentEntryQuery;
use CookieConsentManager\Model\VersionQuery;
use CookieConsentManager\Tests\Support\CcmTestCase;
use Propel\Runtime\Propel;

/**
 * Reinstalling or updating the module must not touch the evidence it holds.
 * No test transaction: the real code path commits.
 */
final class InstallationTest extends CcmTestCase
{
    protected bool $useTransaction = false;

    private const ID = 'install-test-0000';

    protected function tearDown(): void
    {
        ConsentEntryQuery::create()->filterByConsentId(self::ID)->delete();
        parent::tearDown();
    }

    public function testInstallAndUpdateKeepTheRegistryAndTheVersionsAndCanBeReplayed(): void
    {
        (new ConsentEntry())
            ->setConsentId(self::ID)->setRecordedAt(new \DateTimeImmutable())->setAction('custom')->setSource('api')
            ->setVersion(1)->setVersionHash('abcdef012345')->setLocale('fr_FR')->setPath('/')
            ->setPurposesJson('{}')->setServicesJson('{}')
            ->save();
        $entries = ConsentEntryQuery::create()->count();
        $versions = VersionQuery::create()->count();

        $installer = SchemaInstaller::forModule(\dirname(__DIR__, 3));
        $installer->apply();
        $installer->apply();
        $installer->applyUpdates('0.0.1');
        $installer->applyUpdates('0.0.1');

        self::assertSame($entries, ConsentEntryQuery::create()->count());
        self::assertSame($versions, VersionQuery::create()->count());
        self::assertSame(1, ConsentEntryQuery::create()->filterByConsentId(self::ID)->count());
    }

    public function testAnExistingInstallGetsTheRegistryFilterIndexAndReplayingKeepsOneCopy(): void
    {
        $con = Propel::getWriteConnection('TheliaMain');
        $name = 'idx_ccm_consent_entry_action_recorded_at';
        $columns = static fn (): array => array_column($con->query("SHOW INDEX FROM ccm_consent_entry WHERE Key_name = '".$name."'")->fetchAll(\PDO::FETCH_ASSOC), 'Column_name');

        if ([] !== $columns()) {
            $con->exec('ALTER TABLE ccm_consent_entry DROP INDEX '.$name);
        }

        $installer = SchemaInstaller::forModule(\dirname(__DIR__, 3));
        $installer->apply();
        $installer->apply();

        self::assertSame(['action', 'recorded_at'], $columns());
    }

    public function testAnExistingInstallGetsTheFirstChoiceColumnFilledFromItsRegistry(): void
    {
        $con = Propel::getWriteConnection('TheliaMain');
        $insert = $con->prepare(
            'INSERT INTO ccm_consent_entry (consent_id, recorded_at, action, source, version, version_hash, locale, path, purposes_json, services_json)'
            .' VALUES (:consent, :at, \'custom\', \'api\', 1, \'abcdef012345\', \'fr_FR\', \'/\', \'{}\', \'{}\')',
        );
        $con->exec('ALTER TABLE ccm_consent_entry DROP COLUMN first_choice');

        try {
            $insert->execute(['consent' => self::ID, 'at' => '2026-10-01 10:00:00']);
            $insert->execute(['consent' => self::ID, 'at' => '2026-10-02 10:00:00']);
        } finally {
            $installer = SchemaInstaller::forModule(\dirname(__DIR__, 3));
            $installer->apply();
            $installer->apply();
        }

        self::assertSame(
            ['1', '0'],
            array_map('strval', $con->query("SELECT first_choice FROM ccm_consent_entry WHERE consent_id = '".self::ID."' ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN)),
            'the earliest entry of a consent is its first choice, the next one is not',
        );
    }

    public function testAScriptThatWouldDropOrTruncateIsRefusedBeforeAnyStatementRuns(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ccm-sql');
        file_put_contents($file, "CREATE TABLE IF NOT EXISTS ccm_should_not_exist (id INT);\nDROP TABLE IF EXISTS `ccm_consent_entry`;\n");

        try {
            SchemaInstaller::forModule(\dirname(__DIR__, 3))->execute($file);
            self::fail('A destructive script must be refused');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('destroys data', $exception->getMessage());
        } finally {
            unlink($file);
        }

        self::assertFalse(Propel::getReadConnection('TheliaMain')->query("SHOW TABLES LIKE 'ccm_should_not_exist'")->fetch(), 'no statement of the refused script ran');
        self::assertGreaterThanOrEqual(0, ConsentEntryQuery::create()->count());
    }

    public function testTheShippedInstallScriptContainsNoDestructiveStatement(): void
    {
        $sql = (string) file_get_contents(\dirname(__DIR__, 3).'/Config/TheliaMain.sql');
        $statements = preg_replace('/^\s*(#|--).*$/m', '', $sql);

        self::assertDoesNotMatchRegularExpression('/\bDROP\s+TABLE\b|\bTRUNCATE\b|\bDELETE\s+FROM\b/i', (string) $statements);
        self::assertSame(5, preg_match_all('/CREATE TABLE IF NOT EXISTS/', (string) $statements));
    }
}
