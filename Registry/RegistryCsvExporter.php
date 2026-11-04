<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

use CookieConsentManager\Admin\CsvLabeler;
use CookieConsentManager\Model\ConsentEntry;

/** Columns of SPEC annex B, in order; the optional ones only when the matching option is on. */
final readonly class RegistryCsvExporter
{
    public const COLUMNS = ['consent_id', 'recorded_at_utc', 'action', 'source', 'version', 'version_hash', 'locale', 'path', 'browser', 'purposes_json', 'services_json'];

    public function __construct(
        private RegistryOptions $options,
        private EntryRepository $entries,
    ) {
    }

    /** @param resource $stream */
    public function write($stream, ?string $action = null, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null, ?CsvLabeler $labeler = null, string $locale = 'en'): int
    {
        $registry = $this->options->current();
        $withIp = 'truncated' === $registry['ip_mode'];
        $withCustomer = $registry['link_customer'];

        $header = self::COLUMNS;
        if ($withIp) {
            $header[] = 'ip_truncated';
        }
        if ($withCustomer) {
            $header[] = 'customer_id';
        }
        $columns = $header;
        $this->put($stream, null === $labeler ? $header : $labeler->header($header));

        $rows = 0;
        foreach ($this->entries->iterate($action, $from, $to) as $entry) {
            $row = $this->row($entry);
            if ($withIp) {
                $row[] = $entry->getIpTruncated();
            }
            if ($withCustomer) {
                $row[] = $entry->getCustomerId();
            }
            $this->put($stream, null === $labeler ? $row : $labeler->row($columns, $row, $locale));
            ++$rows;
        }

        return $rows;
    }

    /** @return list<scalar|null> */
    private function row(ConsentEntry $entry): array
    {
        return [
            $entry->getConsentId(),
            $entry->getRecordedAt()?->format('Y-m-d\TH:i:s\Z'),
            $entry->getAction(),
            $entry->getSource(),
            $entry->getVersion(),
            $entry->getVersionHash(),
            $entry->getLocale(),
            $entry->getPath(),
            $entry->getBrowser(),
            $entry->getPurposesJson(),
            $entry->getServicesJson(),
        ];
    }

    /**
     * @param resource          $stream
     * @param list<scalar|null> $row
     */
    private function put($stream, array $row): void
    {
        // A cell starting with = + - @ would be run as a formula by a spreadsheet: the path is client input.
        $row = array_map(static fn (mixed $cell): mixed => \is_string($cell) && 1 === preg_match('/^[=+\-@\t\r]/', $cell) ? "'".$cell : $cell, $row);

        fputcsv($stream, $row, ';', '"', '\\');
    }
}
