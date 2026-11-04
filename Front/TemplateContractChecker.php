<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use CookieConsentManager\Publication\OverriddenTemplatesProvider;

/**
 * For the back-office (red alert "contract version differs", SPEC annex E): the theme templates
 * whose declared version is not the one of this module, a missing marker included.
 */
final readonly class TemplateContractChecker
{
    public function __construct(private OverriddenTemplatesProvider $templates)
    {
    }

    /** @return list<array{template: string, source: string, hash: string, contract: string|null}> */
    public function outdated(): array
    {
        return array_values(array_filter(
            $this->templates->list(),
            static fn (array $override): bool => TemplateContext::CONTRACT_VERSION !== ($override['contract'] ?? null),
        ));
    }
}
