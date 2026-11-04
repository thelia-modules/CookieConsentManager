<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use Symfony\Component\Finder\Finder;
use Thelia\Core\Template\TemplateHelperInterface;

/** Looks for fonts served by Google in the active front theme (an IP-leaking request before any choice). */
class ThemeFontScanner
{
    private const PATTERN = '~fonts\.(?:googleapis|gstatic)\.com~i';

    public function __construct(private readonly TemplateHelperInterface $templates)
    {
    }

    /** @return list<string> files of the theme (relative to it) that reference Google fonts */
    public function findings(): array
    {
        $root = $this->templates->getActiveFrontTemplate()->getAbsolutePath();
        if (!is_dir($root)) {
            return [];
        }

        $found = [];
        $finder = (new Finder())->files()->in($root)->exclude(['node_modules', 'vendor', 'public', 'var'])
            ->name(['*.twig', '*.css', '*.scss', '*.html', '*.js'])->size('< 512K');
        foreach ($finder as $file) {
            if (1 === preg_match(self::PATTERN, (string) file_get_contents($file->getPathname()))) {
                $found[] = $file->getRelativePathname();
            }
        }

        return $found;
    }
}
