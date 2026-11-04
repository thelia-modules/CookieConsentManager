<?php

declare(strict_types=1);

namespace CookieConsentManager\Catalogue;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Lifetime that the publisher of a catalogue service documents for some of its cookies, and the one the administrator enters. */
final class CookieDurations
{
    /** @var array<string, array<string, array{duration_days?: int, duration_minutes?: int}>>|null service key => cookie name => lifetime */
    private ?array $durations = null;

    public function __construct(
        #[Autowire(value: __DIR__.'/../Resources/catalogue/cookie-durations.json')]
        private readonly string $path,
    ) {
    }

    /**
     * The cookies of a service with their duration, or an empty list when no duration is known
     * for any of them (the service then keeps the plain list of names). A cookie the catalogue
     * declares as a pattern (`_pk_*`) is replaced by the documented cookies it covers.
     *
     * @param list<string>       $cookies   names the catalogue declares for the service
     * @param array<string, int> $overrides days typed by the administrator, by cookie name or pattern as declared
     *
     * @return list<array{name: string, duration_days?: int, duration_minutes?: int}>
     */
    public function details(string $key, array $cookies, array $overrides = []): array
    {
        $known = $this->all()[$key] ?? [];
        foreach ($overrides as $name => $days) {
            $known[(string) $name] = ['duration_days' => $days];
        }

        $details = [];
        $matched = false;
        foreach ($cookies as $name) {
            $expanded = isset($known[$name]) ? [] : $this->covered($name, $known, $cookies);
            if ([] !== $expanded) {
                $matched = true;
                foreach ($expanded as $coveredName) {
                    $details[$coveredName] = ['name' => $coveredName] + $known[$coveredName];
                }
            } elseif (isset($known[$name])) {
                $matched = true;
                $details[$name] = ['name' => $name] + $known[$name];
            } else {
                $details[$name] = ['name' => $name];
            }
        }

        return $matched ? array_values($details) : [];
    }

    /**
     * For each active service that declares a cookie with no known lifetime, neither documented nor typed:
     * its key and the names of those cookies only.
     *
     * @param list<array<string, mixed>> $services normalized services of a draft
     *
     * @return array<string, list<string>> service key => cookies without a lifetime
     */
    public function withoutLifetime(array $services, CatalogueReader $catalogue): array
    {
        $missing = [];
        foreach ($services as $service) {
            if (!$service['enabled']) {
                continue;
            }
            if (isset($service['custom']) || isset($service['module'])) {
                $details = array_map(static fn (array $cookie): array => ['name' => (string) $cookie['name']] + $cookie, (array) ($service['cookies'] ?? []));
            } else {
                $cookies = $catalogue->find($service['key'])->cookies ?? [];
                $details = $this->details($service['key'], $cookies, (array) ($service['cookie_durations'] ?? []));
                if ([] === $details) {
                    $details = array_map(static fn (string $name): array => ['name' => $name], $cookies);
                }
            }
            $names = array_column(array_filter($details, static fn (array $cookie): bool => !isset($cookie['duration_days']) && !isset($cookie['duration_minutes'])), 'name');
            if ([] !== $names) {
                $missing[$service['key']] = array_values(array_map('strval', $names));
            }
        }

        return $missing;
    }

    /** @return array<string, array<string, array{duration_days?: int, duration_minutes?: int}>> */
    public function all(): array
    {
        if (null !== $this->durations) {
            return $this->durations;
        }

        $content = is_file($this->path) ? file_get_contents($this->path) : false;
        $this->durations = [];
        foreach (false === $content ? [] : (array) json_decode($content, true, 512, \JSON_THROW_ON_ERROR) as $key => $cookies) {
            foreach ((array) $cookies as $cookie) {
                $this->durations[(string) $key][(string) $cookie['name']] = isset($cookie['duration_minutes'])
                    ? ['duration_minutes' => (int) $cookie['duration_minutes']]
                    : ['duration_days' => (int) $cookie['duration_days']];
            }
        }

        return $this->durations;
    }

    /**
     * Whether a name is covered by a wildcard pattern.
     */
    public static function covers(string $pattern, string $name): bool
    {
        return str_contains($pattern, '*') && fnmatch($pattern, $name);
    }

    /**
     * @param array<string, mixed> $known
     * @param list<string>         $declared
     *
     * @return list<string>
     */
    private function covered(string $pattern, array $known, array $declared): array
    {
        if (!str_contains($pattern, '*')) {
            return [];
        }

        return array_values(array_filter(array_keys($known), static fn (string $name): bool => !\in_array($name, $declared, true) && self::covers($pattern, $name)));
    }
}
