<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

final class ConfigDiff
{
    /**
     * Leaf paths that differ between two configurations (added, removed or changed),
     * services keyed by their key so that the order never matters.
     *
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     *
     * @return list<string>
     */
    public static function between(array $left, array $right): array
    {
        $a = self::flatten(self::withoutMeta($left));
        $b = self::flatten(self::withoutMeta($right));

        $paths = array_unique([...array_keys($a), ...array_keys($b)]);
        sort($paths);

        return array_values(array_filter(
            $paths,
            static fn (string $path): bool => (!\array_key_exists($path, $a) || !\array_key_exists($path, $b) || $a[$path] !== $b[$path]) && !self::emptyAgainstMissing($path, $a, $b),
        ));
    }

    /**
     * @param array<string, scalar|null> $a
     * @param array<string, scalar|null> $b
     */
    private static function emptyAgainstMissing(string $path, array $a, array $b): bool
    {
        return (!\array_key_exists($path, $a) && '[]' === $b[$path]) || (!\array_key_exists($path, $b) && '[]' === $a[$path]);
    }

    /**
     * The same leaf paths with the value on each side, null where the path does not exist.
     *
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     *
     * @return array<string, array{before: scalar|null, after: scalar|null}>
     */
    public static function changes(array $left, array $right): array
    {
        $a = self::flatten(self::withoutMeta($left));
        $b = self::flatten(self::withoutMeta($right));

        $changes = [];
        foreach (self::between($left, $right) as $path) {
            $changes[$path] = ['before' => $a[$path] ?? null, 'after' => $b[$path] ?? null];
        }

        return $changes;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private static function withoutMeta(array $config): array
    {
        unset($config['_meta']);

        $services = [];
        foreach ((array) ($config['services'] ?? []) as $service) {
            $services[$service['key']] = $service;
        }
        $config['services'] = $services;

        return $config;
    }

    /**
     * @param array<mixed> $value
     *
     * @return array<string, scalar|null>
     */
    public static function flatten(array $value, string $prefix = ''): array
    {
        $flat = [];
        foreach ($value as $key => $item) {
            $path = '' === $prefix ? (string) $key : $prefix.'.'.$key;
            if (\is_array($item) && [] !== $item) {
                $flat += self::flatten($item, $path);
            } else {
                $flat[$path] = \is_array($item) ? '[]' : $item;
            }
        }

        return $flat;
    }
}
