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

    public function apply(?ConnectionInterface $con = null): void
    {
        $this->execute($this->moduleDirectory.'/Config/TheliaMain.sql', $con);
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
