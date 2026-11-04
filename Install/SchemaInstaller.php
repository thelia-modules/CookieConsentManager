<?php

declare(strict_types=1);

namespace CookieConsentManager\Install;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;

final readonly class SchemaInstaller
{
    public function __construct(private string $moduleDirectory)
    {
    }

    public static function forModule(string $moduleDirectory): self
    {
        return new self($moduleDirectory);
    }

    // Indexes added after the first release: CREATE TABLE IF NOT EXISTS leaves an existing table untouched.
    private const LATER_INDEXES = [
        ['ccm_consent_entry', 'idx_ccm_consent_entry_action_recorded_at', '`action`, `recorded_at`'],
    ];

    // Columns added after the first release, with the statement that fills them for the rows already there.
    private const LATER_COLUMNS = [
        [
            'ccm_consent_entry',
            'first_choice',
            'TINYINT(1) DEFAULT 0 NOT NULL',
            'UPDATE `ccm_consent_entry` e LEFT JOIN `ccm_consent_entry` earlier ON earlier.consent_id = e.consent_id AND earlier.id < e.id SET e.first_choice = 1 WHERE earlier.id IS NULL',
        ],
    ];

    public function apply(?ConnectionInterface $con = null): void
    {
        $con ??= Propel::getWriteConnection('TheliaMain');
        $this->execute($this->moduleDirectory.'/Config/TheliaMain.sql', $con);

        foreach (self::LATER_INDEXES as [$table, $index, $columns]) {
            $exists = $con->query(\sprintf("SHOW INDEX FROM `%s` WHERE Key_name = '%s'", $table, $index))->fetch();
            if (false === $exists) {
                $con->exec(\sprintf('ALTER TABLE `%s` ADD INDEX `%s` (%s)', $table, $index, $columns));
            }
        }

        foreach (self::LATER_COLUMNS as [$table, $column, $definition, $fill]) {
            $exists = $con->query(\sprintf("SHOW COLUMNS FROM `%s` LIKE '%s'", $table, $column))->fetch();
            if (false === $exists) {
                $con->exec(\sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));
                $con->exec($fill);
            }
        }
    }

    public function applyUpdates(string $currentVersion, ?ConnectionInterface $con = null): void
    {
        $files = glob($this->moduleDirectory.'/Config/update/*.sql') ?: [];
        sort($files, \SORT_NATURAL);

        foreach ($files as $file) {
            if (version_compare($currentVersion, basename($file, '.sql'), '<')) {
                $this->execute($file, $con);
            }
        }
    }

    public function execute(string $file, ?ConnectionInterface $con = null): void
    {
        $sql = file_get_contents($file);
        if (false === $sql) {
            throw new \RuntimeException(\sprintf('Cannot read %s', $file));
        }

        $statements = $this->statements($sql);

        if (1 === preg_match('/\bDROP\s+(TABLE|DATABASE)\b|\bTRUNCATE\b/i', implode(";\n", $statements))) {
            throw new \RuntimeException(\sprintf('%s destroys data: the registry and the published versions must survive an installation.', basename($file)));
        }

        $con ??= Propel::getWriteConnection('TheliaMain');

        foreach ($statements as $statement) {
            $con->exec($statement);
        }
    }

    /** @return list<string> */
    private function statements(string $sql): array
    {
        $withoutComments = preg_replace('/^\s*(#|--).*$/m', '', $sql) ?? $sql;

        return array_values(array_filter(
            array_map('trim', explode(';', $withoutComments)),
            static fn (string $statement): bool => '' !== $statement,
        ));
    }
}
