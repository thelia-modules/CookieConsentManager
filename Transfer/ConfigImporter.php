<?php

declare(strict_types=1);

namespace CookieConsentManager\Transfer;

use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Draft\DraftNormalizer;
use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Validation\DraftValidator;
use CookieConsentManager\Validation\ValidationIssue;

final readonly class ConfigImporter
{
    public function __construct(
        private ConfigSchemaValidator $schema,
        private DraftNormalizer $normalizer,
        private DraftRepository $drafts,
        private CatalogueReader $catalogue,
        private DraftValidator $validator,
        private PolicyUrlResolver $policy,
    ) {
    }

    /**
     * Creates a draft, never a publication.
     *
     * @throws ImportRefusedException
     */
    public function import(string $json, ?string $author): ImportReport
    {
        try {
            $document = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ImportRefusedException(['json: '.$exception->getMessage()]);
        }
        if (!\is_array($document)) {
            throw new ImportRefusedException(['json: the document must be an object']);
        }

        if (ConfigExporter::SCHEMA !== ($document['schema'] ?? null)) {
            throw new ImportRefusedException(['schema: unknown schema "'.(\is_scalar($document['schema'] ?? null) ? $document['schema'] : '').'", expected '.ConfigExporter::SCHEMA]);
        }

        $violations = $this->schema->violations($json);
        if ([] !== $violations) {
            throw new ImportRefusedException($violations);
        }

        $draft = array_diff_key($document, array_flip(['schema', 'exported_at', 'module_version', 'source_version', 'environment']));
        $draft = $this->normalizer->normalize($draft);

        $skipped = [];
        if (!$draft['general']['experimental_services']) {
            $draft['services'] = array_values(array_filter($draft['services'], function (array $service) use (&$skipped): bool {
                $entry = isset($service['custom']) || isset($service['module']) ? null : $this->catalogue->find($service['key']);
                if (null !== $entry && !$entry->verified) {
                    $skipped[] = $service['key'];

                    return false;
                }

                return true;
            }));
        }

        $dangerous = array_values(array_filter(
            $this->validator->validate($draft),
            static fn (ValidationIssue $issue): bool => $issue->isError() && \in_array($issue->code, [...DraftValidator::URL_ERROR_CODES, ...DraftValidator::MODULE_ERROR_CODES], true),
        ));
        if ([] !== $dangerous) {
            throw new ImportRefusedException(array_map(static fn (ValidationIssue $issue): string => $issue->field.': '.$issue->message, $dangerous));
        }

        $pending = $this->environmentFields($draft);
        if ([] !== $pending) {
            $draft['_meta']['environment_pending'] = $pending;
        }

        $this->drafts->replace($draft, $author);

        $url = $draft['policy']['url'];

        return new ImportReport(
            $pending,
            $skipped,
            null === $url || '' === $url ? null : $this->policy->resolves($url),
            $this->validator->validate($this->drafts->load()),
        );
    }

    /**
     * @param array<string, mixed> $draft
     *
     * @return list<string>
     */
    private function environmentFields(array $draft): array
    {
        $fields = [];

        if (null !== $draft['policy']['url'] && '' !== $draft['policy']['url']) {
            $fields[] = 'policy.url';
        }
        if ([] !== $draft['consent_mode']['regions']) {
            $fields[] = 'consent_mode.regions';
        }
        foreach ($draft['services'] as $service) {
            foreach (($service['parameters'] ?? []) as $name => $value) {
                if ('' !== (string) $value) {
                    $fields[] = 'services.'.$service['key'].'.parameters.'.$name;
                }
            }
        }

        return $fields;
    }
}
