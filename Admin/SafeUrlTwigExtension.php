<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Validation\DraftValidator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/** An address stored in the configuration only becomes a link when it is safe to follow. */
final class SafeUrlTwigExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('ccm_safe_url', static fn (string $url): string => DraftValidator::isPolicyUrl($url) ? $url : '#')];
    }
}
