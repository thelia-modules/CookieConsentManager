<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Draft\DraftNormalizer;
use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Validation\DraftValidator;
use CookieConsentManager\Validation\ValidationIssue;

/**
 * Stores a candidate draft only when the section being saved holds no error: an error in
 * another section never blocks it, but a refused candidate is never written.
 */
final readonly class DraftWriter
{
    public function __construct(
        private DraftRepository $drafts,
        private DraftNormalizer $normalizer,
        private DraftValidator $validator,
        private ContrastChecker $contrast,
    ) {
    }

    /**
     * @param array<string, mixed> $candidate
     * @param list<string>         $prefixes  field prefixes of the section
     */
    public function save(array $candidate, array $prefixes, ?string $author, bool $checkContrast = false): SaveResult
    {
        $candidate = $this->normalizer->normalize($candidate);
        $issues = array_values(array_filter(
            $this->validator->validate($candidate),
            static function (ValidationIssue $issue) use ($prefixes): bool {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($issue->field, $prefix)) {
                        return true;
                    }
                }

                return false;
            },
        ));

        $findings = $checkContrast ? $this->contrast->check($candidate['appearance']['colors']) : [];
        $refused = DraftValidator::hasErrors($issues) || ($checkContrast && ContrastChecker::blocking($findings));

        if ($refused) {
            return new SaveResult(false, $candidate, $issues, $findings);
        }

        $this->drafts->replace($candidate, $author);

        return new SaveResult(true, $this->drafts->load(), $issues, $findings);
    }
}
