<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

use CookieConsentManager\Model\DailyStatQuery;
use Propel\Runtime\Propel;

final readonly class DailyStatRepository
{
    /** @param list<string> $metrics */
    public function increment(\DateTimeInterface $day, array $metrics): void
    {
        $statement = Propel::getWriteConnection('TheliaMain')->prepare(
            'INSERT INTO ccm_daily_stat (day, metric, total) VALUES (:day, :metric, 1) ON DUPLICATE KEY UPDATE total = total + 1',
        );

        foreach ($metrics as $metric) {
            $statement->execute(['day' => $day->format('Y-m-d'), 'metric' => $metric]);
        }
    }

    /** @return array<string, int> metric => total over the period */
    public function totals(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $totals = [];
        foreach (DailyStatQuery::create()->filterByDay(['min' => $from, 'max' => $to])->find() as $row) {
            $totals[$row->getMetric()] = ($totals[$row->getMetric()] ?? 0) + $row->getTotal();
        }

        return $totals;
    }

    /**
     * Recomputes from the registry the first choices of the days it still covers; older days, whose entries
     * were purged, are kept. Robots cannot be told apart in the registry (only the browser family is kept),
     * so they count as visitors here.
     *
     * @return int first choices counted
     */
    public function rebuildFromRegistry(): int
    {
        $con = Propel::getWriteConnection('TheliaMain');
        $oldest = $con->query('SELECT MIN(DATE(recorded_at)) FROM ccm_consent_entry')->fetchColumn();
        if (!\is_string($oldest)) {
            return 0;
        }

        // A first choice is flagged when it is recorded: once the purge has removed the first entry of a consent,
        // its next entry has no earlier one left but is still a change of mind, not a first choice.
        $select = $con->prepare(
            'SELECT e.id, e.recorded_at, e.action, e.purposes_json, e.services_json FROM ccm_consent_entry e'
            .' WHERE e.id > :last AND e.first_choice = 1'
            .' ORDER BY e.id LIMIT 500',
        );

        $counts = [];
        $counted = 0;
        $last = 0;
        do {
            $select->execute(['last' => $last]);
            $rows = $select->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $last = (int) $row['id'];
                $day = substr((string) $row['recorded_at'], 0, 10);
                foreach (FirstChoiceMetrics::of((string) $row['action'], $this->states((string) $row['purposes_json']), $this->states((string) $row['services_json'])) as $metric) {
                    $counts[$day][$metric] = ($counts[$day][$metric] ?? 0) + 1;
                }
                ++$counted;
            }
        } while ([] !== $rows);

        $con->beginTransaction();

        try {
            $delete = $con->prepare('DELETE FROM ccm_daily_stat WHERE day >= :oldest');
            $delete->execute(['oldest' => $oldest]);
            $insert = $con->prepare('INSERT INTO ccm_daily_stat (day, metric, total) VALUES (:day, :metric, :total)');
            foreach ($counts as $day => $metrics) {
                foreach ($metrics as $metric => $total) {
                    $insert->execute(['day' => $day, 'metric' => $metric, 'total' => $total]);
                }
            }
            $con->commit();
        } catch (\Throwable $exception) {
            $con->rollBack();

            throw $exception;
        }

        return $counted;
    }

    /** @return array<string, int> */
    private function states(string $json): array
    {
        $decoded = json_decode($json, true);

        return \is_array($decoded) ? array_map('intval', $decoded) : [];
    }
}
