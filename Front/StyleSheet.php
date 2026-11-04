<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The stylesheet of the module, served by the module itself (nothing is installed in public/, the themes compile their own
 * CSS and never see the templates of a module) under a name made of its own hash: whoever changes it changes the URL, so the
 * file is immutable. It is linked from the head, where it blocks the first paint like inline CSS would: the banner is never
 * shown unstyled, and a visitor pays the request once.
 */
final class StyleSheet
{
    private ?string $minified = null;

    public function __construct(
        #[Autowire(value: __DIR__.'/../assets/css/ccm.css')]
        private readonly string $path,
    ) {
    }

    public const HASH_LENGTH = 12;

    public function minified(): string
    {
        if (null === $this->minified) {
            $css = is_file($this->path) ? (string) file_get_contents($this->path) : '';
            $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);
            $css = (string) preg_replace('/\s+/', ' ', $css);
            $css = (string) preg_replace('/\s*([{};,>])\s*/', '$1', $css);
            // Only the space after a colon goes: before it, `a :hover` and `a:hover` are not the same selector.
            $this->minified = trim(str_replace([': ', ';}'], [':', '}'], $css));
        }

        return $this->minified;
    }

    public function hash(): string
    {
        return substr(hash('sha256', $this->minified()), 0, self::HASH_LENGTH);
    }
}
