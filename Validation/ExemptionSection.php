<?php

declare(strict_types=1);

namespace CookieConsentManager\Validation;

use CookieConsentManager\Admin\CodeLabels;
use CookieConsentManager\Catalogue\CatalogueReader;

/** The measurement tools exempted from consent. */
final readonly class ExemptionSection implements SectionValidator
{
    public function __construct(
        private CatalogueReader $catalogue,
        private IssueFactory $issues,
    ) {
    }

    public static function priority(): int
    {
        return 800;
    }

    public function validate(array $draft): array
    {
        $issues = [];
        $this->exempt($draft, $issues);

        return $issues;
    }

    /**
     * @param array<string, mixed>  $draft
     * @param list<ValidationIssue> $issues
     */
    private function exempt(array $draft, array &$issues): void
    {
        $services = array_column($draft['services'], null, 'key');

        foreach ($draft['exempt_measurement'] as $index => $item) {
            $field = 'exempt_measurement.'.$index;
            $service = $services[$item['service']] ?? null;
            $entry = $this->catalogue->find($item['service']);

            if (null === $service || 'analytics' !== $service['purpose']) {
                $issues[] = $this->issues->error('exempt_service', $field, 'Only a configured audience measurement service can be exempted.');
                continue;
            }
            if ((null !== $entry && $entry->googleTag) || 'gtag' === $item['service']) {
                $issues[] = $this->issues->error('exempt_google', $field, 'A Google Analytics service is never eligible to the exemption.');
            }
            if (!(null !== $entry && $entry->googleTag) && 'gtag' !== $item['service'] && !ExemptionWhitelist::allows($item['service'])) {
                $issues[] = $this->issues->error('exempt_not_allowed', $field, 'This service is not in the list of tools that can be exempted from consent.');
            }
            $missing = array_keys(array_filter($item['conditions'], static fn (bool $confirmed): bool => !$confirmed));
            if ([] !== $missing) {
                $labels = array_map(fn (string $condition): string => $this->issues->trans(CodeLabels::english('condition', $condition)), $missing);
                $issues[] = $this->issues->error('exempt_conditions', $field, 'Condition(s) not confirmed: %conditions%.', ['%conditions%' => implode(', ', $labels)]);
            }
        }
    }
}
