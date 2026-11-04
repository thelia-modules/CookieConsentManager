<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The stylesheet of the module is printed in the head instead of being linked: the banner is part of
 * the first paint, a request on the critical path would delay it, and nothing has to be
 * installed in public/ (the themes compile their own CSS and never see the templates of a module).
 */
final class StyleSheet
{
    private ?string $minified = null;

    public function __construct(
        #[Autowire(value: __DIR__.'/../assets/css/ccm.css')]
        private readonly string $path,
    ) {
    }

    public function minified(): string
    {
        if (null === $this->minified) {
            $css = is_file($this->path) ? (string) file_get_contents($this->path) : '';
            $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);
            $css = (string) preg_replace('/\s+/', ' ', $css);
            $this->minified = trim((string) preg_replace('/\s*([{};,>])\s*/', '$1', $css));
        }

        return $this->minified;
    }
}
