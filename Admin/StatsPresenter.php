<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Registry\DailyStatRepository;
use CookieConsentManager\Registry\FirstChoiceMetrics;

/** First choices of an identifier over a period, as rates (the first entry of an identifier in the registry, counted on the day it was recorded). */
final readonly class StatsPresenter
{
    public const PERIODS = [7, 30, 90];

    public function __construct(private DailyStatRepository $stats)
    {
    }

    /**
     * @return array{days: int, total: int, robots: int, rates: array<string, float|null>, purposes: array<string, array{count: int, rate: float|null}>, services: array<string, array{count: int, rate: float|null}>}
     */
    public function present(int $days, ?\DateTimeImmutable $now = null): array
    {
        $days = \in_array($days, self::PERIODS, true) ? $days : 30;
        $to = $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $totals = $this->stats->totals($to->modify(\sprintf('-%d days', $days - 1))->setTime(0, 0), $to);
        $total = $totals['first_total'] ?? 0;

        $rate = static fn (int $count): ?float => $total > 0 ? round($count / $total * 100, 1) : null;
        $group = static function (string $prefix) use ($totals, $rate): array {
            $rows = [];
            foreach ($totals as $metric => $count) {
                if (str_starts_with($metric, $prefix)) {
                    $rows[substr($metric, \strlen($prefix))] = ['count' => $count, 'rate' => $rate($count)];
                }
            }
            ksort($rows);

            return $rows;
        };

        return [
            'days' => $days,
            'total' => $total,
            'robots' => $totals[FirstChoiceMetrics::ROBOT] ?? 0,
            'rates' => [
                'accept_all' => $rate($totals['first_accept_all'] ?? 0),
                'reject_all' => $rate($totals['first_reject_all'] ?? 0),
                'reject_panel' => $rate($totals[FirstChoiceMetrics::REJECT_PANEL] ?? 0),
                'refusal' => $rate(($totals['first_reject_all'] ?? 0) + ($totals[FirstChoiceMetrics::REJECT_PANEL] ?? 0)),
                'custom' => $rate($totals['first_custom'] ?? 0),
            ],
            'purposes' => $group('purpose:'),
            'services' => $group('service:'),
        ];
    }
}
