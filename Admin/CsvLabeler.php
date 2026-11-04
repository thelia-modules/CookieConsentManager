<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Draft\DraftRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Header and values of the registry export in the language of the back-office, for a reader who is not a developer. */
final readonly class CsvLabeler
{
    private const DOMAIN = 'cookieconsentmanager.bo.default-twig';

    private const HEADERS = [
        'consent_id' => 'Consent identifier',
        'recorded_at_utc' => 'Date and time (UTC)',
        'action' => 'Action',
        'source' => 'Source',
        'version' => 'Version',
        'version_hash' => 'Version fingerprint',
        'locale' => 'Language',
        'path' => 'Page',
        'browser' => 'Browser',
        'purposes_json' => 'Purposes',
        'services_json' => 'Services',
        'ip_truncated' => 'Truncated IP address',
    ];

    public function __construct(
        private DraftRepository $drafts,
        private CatalogueReader $catalogue,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param list<string> $columns
     *
     * @return list<string>
     */
    public function header(array $columns): array
    {
        return array_map(fn (string $column): string => $this->t(self::HEADERS[$column] ?? $column), $columns);
    }

    /**
     * @param list<string>      $columns
     * @param list<scalar|null> $row
     *
     * @return list<scalar|null>
     */
    public function row(array $columns, array $row, string $locale): array
    {
        $draft = $this->drafts->load();
        foreach ($columns as $index => $column) {
            $value = $row[$index] ?? null;
            $row[$index] = match ($column) {
                'action' => $this->t(CodeLabels::english('action', (string) $value)),
                'source' => $this->t(CodeLabels::english('source', (string) $value)),
                'recorded_at_utc' => \is_string($value) ? str_replace(['T', 'Z'], [' ', ''], $value) : $value,
                'purposes_json' => $this->states((string) $value, static function (string $code) use ($draft, $locale): string {
                    foreach ($draft['purposes'] as $purpose) {
                        if ($purpose['code'] === $code) {
                            return (string) ($purpose['label'][$locale] ?? $purpose['label']['en'] ?? $code);
                        }
                    }

                    return $code;
                }),
                'services_json' => $this->states((string) $value, fn (string $key): string => $this->catalogue->find($key)->name ?? $key),
                default => $value,
            };
        }

        return $row;
    }

    /** @param callable(string): string $name */
    private function states(string $json, callable $name): string
    {
        $parts = [];
        foreach ((array) json_decode($json, true) as $code => $state) {
            $parts[] = $name((string) $code).' : '.$this->t(1 === (int) $state ? 'granted' : 'refused');
        }

        return implode(' ; ', $parts);
    }

    private function t(string $message): string
    {
        return $this->translator->trans($message, [], self::DOMAIN);
    }
}
