<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Model\ConsentEntryQuery;
use CookieConsentManager\Registry\DailyStatRepository;
use CookieConsentManager\Registry\FirstChoiceMetrics;
use CookieConsentManager\Tests\Support\CcmTestCase;
use Propel\Runtime\Propel;

final class StatsRebuildTest extends CcmTestCase
{
    private const CONSENTS = 800;
    private const ENTRIES = 1300;

    public function testRebuildCountsOnlyTheFirstChoiceOfEachConsentAcrossSeveralBatches(): void
    {
        $con = Propel::getWriteConnection('TheliaMain');
        ConsentEntryQuery::create()->deleteAll();
        $con->exec('DELETE FROM ccm_daily_stat');

        $insert = $con->prepare(
            'INSERT INTO ccm_consent_entry (consent_id, recorded_at, action, source, version, version_hash, locale, path, purposes_json, services_json)'
            .' VALUES (:consent, :at, :action, \'banner\', 1, \'abcdef012345\', \'fr_FR\', \'/p\', :purposes, :services)',
        );
        $expected = [];
        for ($i = 0; $i < self::ENTRIES; ++$i) {
            $first = $i < self::CONSENTS;
            $day = (new \DateTimeImmutable('2026-09-01'))->modify(\sprintf('+%d days', intdiv($i, 300)))->format('Y-m-d');
            $action = $first ? ['accept_all', 'reject_all', 'custom', 'custom'][$i % 4] : 'accept_all';
            $purposes = $first && 2 === $i % 4 ? ['analytics' => 1, 'media' => 0] : ($first ? ['analytics' => (int) ('accept_all' === $action), 'media' => 0] : ['analytics' => 1, 'media' => 1]);
            $services = $first ? ['gtag' => $purposes['analytics']] : ['gtag' => 1, 'youtube' => 1];
            $insert->execute([
                'consent' => 'k-'.($i % self::CONSENTS),
                'at' => $day.' 10:00:00',
                'action' => $action,
                'purposes' => json_encode($purposes, \JSON_THROW_ON_ERROR),
                'services' => json_encode($services, \JSON_THROW_ON_ERROR),
            ]);
            if ($first) {
                foreach (FirstChoiceMetrics::of($action, $purposes, $services) as $metric) {
                    $expected[$day][$metric] = ($expected[$day][$metric] ?? 0) + 1;
                }
            }
        }

        self::assertSame(self::CONSENTS, (new DailyStatRepository())->rebuildFromRegistry(), 'one first choice per consent, the later entries are ignored');

        $actual = [];
        foreach ($con->query('SELECT day, metric, total FROM ccm_daily_stat')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $actual[$row['day']][$row['metric']] = (int) $row['total'];
        }
        ksort($expected);
        ksort($actual);
        foreach ($expected as &$metrics) {
            ksort($metrics);
        }
        foreach ($actual as &$metrics) {
            ksort($metrics);
        }
        unset($metrics);
        self::assertSame($expected, $actual);
        self::assertCount(3, $actual, 'the registry spans several days');
    }
}
