<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Admin\AlertProvider\AlertProvider;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** The alerts of the dashboard (docs/administrator-guide.md, "Dashboard"): each one is a measured fact, with where to act. */
final readonly class AlertCollector
{
    public const DOMAIN = 'cookieconsentmanager.bo.default-twig';

    /** @param iterable<AlertProvider> $providers */
    public function __construct(
        #[AutowireIterator(AlertProvider::TAG, defaultPriorityMethod: 'priority')]
        private iterable $providers,
    ) {
    }

    /**
     * @param array<string, mixed> $draft
     *
     * @return list<Alert>
     */
    public function collect(array $draft): array
    {
        $alerts = [];
        foreach ($this->providers as $provider) {
            array_push($alerts, ...$provider->alerts($draft));
        }

        usort($alerts, static fn (Alert $a, Alert $b): int => [self::rank($a->severity)] <=> [self::rank($b->severity)]);

        return $alerts;
    }

    private static function rank(string $severity): int
    {
        return match ($severity) {
            Alert::DANGER => 0,
            Alert::WARNING => 1,
            default => 2,
        };
    }
}
