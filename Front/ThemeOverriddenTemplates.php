<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use CookieConsentManager\Publication\OverriddenTemplatesProvider;
use Thelia\Core\Template\TemplateHelperInterface;

/** Banner templates the active front theme (or one of its parents) provides instead of the module's. */
final readonly class ThemeOverriddenTemplates implements OverriddenTemplatesProvider
{
    public function __construct(
        private TemplateHelperInterface $templates,
        private OverrideScanner $scanner,
    ) {
    }

    public function list(): array
    {
        $theme = $this->templates->getActiveFrontTemplate();
        $paths = [$theme->getName() => $theme->getAbsolutePath()];
        foreach ($theme->getParentList() ?? [] as $parent) {
            $paths[$parent->getName()] ??= $parent->getAbsolutePath();
        }

        return $this->scanner->scan($paths);
    }
}
