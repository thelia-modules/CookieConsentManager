<?php

declare(strict_types=1);

namespace CookieConsentManager\Transfer;

/**
 * The values an import brings from another site (identifiers, policy page, regions) and that
 * stay pending until someone states they are right for this site, or types the right one.
 */
final readonly class EnvironmentFields
{
    /**
     * @param array<string, mixed> $draft
     *
     * @return list<array{path: string, value: string, editable: bool, regions?: list<array{iso: list<string>, default: array<string, string>}>}>
     */
    public function pending(array $draft): array
    {
        $fields = [];
        foreach ((array) ($draft['_meta']['environment_pending'] ?? []) as $path) {
            $path = (string) $path;
            $field = ['path' => $path, 'value' => $this->display($draft, $path), 'editable' => 'consent_mode.regions' !== $path];
            if ('consent_mode.regions' === $path) {
                $field['regions'] = array_map(
                    static fn (array $region): array => ['iso' => array_values((array) $region['iso']), 'default' => array_map('strval', (array) ($region['default'] ?? []))],
                    array_values((array) $draft['consent_mode']['regions']),
                );
            }
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * A field leaves the pending list when it is confirmed as it stands, or when a new value is typed
     * (a value left empty confirms nothing). Nothing is stored here.
     *
     * @param array<string, mixed>  $draft
     * @param list<string>          $confirmed paths the user ticked
     * @param array<string, string> $values    new value by path
     *
     * @return array{draft: array<string, mixed>, resolved: list<string>}
     */
    public function resolve(array $draft, array $confirmed, array $values): array
    {
        $pending = array_map('strval', (array) ($draft['_meta']['environment_pending'] ?? []));
        $resolved = [];

        foreach ($pending as $path) {
            $typed = trim((string) ($values[$path] ?? ''));
            if ('' !== $typed && 'consent_mode.regions' !== $path) {
                $draft = $this->write($draft, $path, $typed);
                $resolved[] = $path;
            } elseif (\in_array($path, $confirmed, true)) {
                $resolved[] = $path;
            }
        }

        $remaining = array_values(array_diff($pending, $resolved));
        if ([] === $remaining) {
            unset($draft['_meta']['environment_pending']);
        } else {
            $draft['_meta']['environment_pending'] = $remaining;
        }

        return ['draft' => $draft, 'resolved' => $resolved];
    }

    /** @param array<string, mixed> $draft */
    private function display(array $draft, string $path): string
    {
        if ('consent_mode.regions' === $path) {
            return json_encode($draft['consent_mode']['regions'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) ?: '';
        }
        if ('policy.url' === $path) {
            return (string) $draft['policy']['url'];
        }

        foreach ($draft['services'] as $service) {
            if (1 === preg_match('/^services\.'.preg_quote((string) $service['key'], '/').'\.parameters\.(.+)$/D', $path, $match)) {
                return (string) ($service['parameters'][$match[1]] ?? '');
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $draft
     *
     * @return array<string, mixed>
     */
    private function write(array $draft, string $path, string $value): array
    {
        if ('policy.url' === $path) {
            $draft['policy']['url'] = $value;

            return $draft;
        }

        foreach ($draft['services'] as $index => $service) {
            if (1 === preg_match('/^services\.'.preg_quote((string) $service['key'], '/').'\.parameters\.(.+)$/D', $path, $match)) {
                $draft['services'][$index]['parameters'][$match[1]] = $value;
            }
        }

        return $draft;
    }
}
