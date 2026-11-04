<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

use CookieConsentManager\Draft\DefaultSettings;

/**
 * Every read of a published snapshot goes through here: a snapshot is frozen when it is written
 * and never migrated in the database, so the code reading it brings it to the shape it expects.
 *
 * A field added by a later release gets its default in the old snapshots. That default must
 * reproduce what the old version did before the field existed, otherwise the script served for
 * that version, and its hash, would change.
 * A change that cannot be a default gets a new schema and a branch below, migrating the previous one.
 */
final readonly class SnapshotNormalizer
{
    /** Sections of the configuration with a fixed set of keys; the others (texts, purposes, services...) hold the data of the site. */
    private const FIXED_SECTIONS = ['general', 'policy', 'consent_mode', 'appearance', 'registry'];

    /**
     * @param array<string, mixed> $snapshot
     *
     * @return array<string, mixed>
     */
    public function normalize(array $snapshot): array
    {
        $schema = $snapshot['schema'] ?? SnapshotAssembler::SCHEMA;

        return match ($schema) {
            SnapshotAssembler::SCHEMA => $this->fromSchema1($snapshot),
            default => throw new \UnexpectedValueException(\sprintf('Unknown snapshot schema "%s": the module is older than the version that published it.', \is_scalar($schema) ? $schema : get_debug_type($schema))),
        };
    }

    /**
     * @param array<string, mixed> $snapshot
     *
     * @return array<string, mixed>
     */
    private function fromSchema1(array $snapshot): array
    {
        $defaults = DefaultSettings::all();
        foreach (self::FIXED_SECTIONS as $section) {
            $snapshot['config'][$section] = $this->withDefaults($defaults[$section], (array) ($snapshot['config'][$section] ?? []));
        }

        return $snapshot;
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function withDefaults(array $defaults, array $values): array
    {
        foreach ($defaults as $key => $default) {
            if (!\array_key_exists($key, $values)) {
                $values[$key] = $default;
            } elseif (\is_array($default) && [] !== $default && !array_is_list($default) && \is_array($values[$key])) {
                $values[$key] = $this->withDefaults($default, $values[$key]);
            }
        }

        // The order of the keys of a stored snapshot, which CanonicalJson sorts
        if (!array_is_list($values)) {
            ksort($values);
        }

        return $values;
    }
}
