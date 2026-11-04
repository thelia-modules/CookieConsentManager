<?php

declare(strict_types=1);

namespace CookieConsentManager\Install;

use CookieConsentManager\Registry\LastPurgeStore;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Model\ConfigQuery;

/** What "delete the module data" removes on uninstall: the five tables and the configuration key of the module. */
final readonly class DataEraser
{
    public const TABLES = ['ccm_consent_entry', 'ccm_daily_stat', 'ccm_draft_setting', 'ccm_service_config', 'ccm_version'];

    public function erase(?ConnectionInterface $con = null): void
    {
        $con ??= Propel::getWriteConnection('TheliaMain');

        // Before the tables: a DROP TABLE commits the transaction of the uninstall.
        ConfigQuery::create()->filterByName(LastPurgeStore::KEY)->delete($con);
        ConfigQuery::resetCache();

        foreach (self::TABLES as $table) {
            $con->exec(\sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
    }
}
