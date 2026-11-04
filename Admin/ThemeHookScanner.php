<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Thelia\Core\Template\TemplateHelperInterface;

/**
 * The module injects its head script and its banner through the theme hooks `layout.head.top` and
 * `layout.body.top`: a theme that calls neither shows nothing, silently. The templates of the
 * active theme and of its parents are searched for the hook names; the result is cached against
 * the size and modification time of the files, so a fix in the theme is seen at the next visit.
 */
final readonly class ThemeHookScanner
{
    public const HOOKS = ['layout.head.top', 'layout.body.top'];

    public function __construct(
        private TemplateHelperInterface $templates,
        #[Autowire(service: 'cache.app')]
        private CacheInterface $cache,
    ) {
    }

    /** @return list<string> the hooks the active front theme never calls */
    public function missing(): array
    {
        $theme = $this->templates->getActiveFrontTemplate();
        $roots = [$theme->getAbsolutePath()];
        foreach ($theme->getParentList() ?? [] as $parent) {
            $roots[] = $parent->getAbsolutePath();
        }
        $roots = array_values(array_filter(array_unique($roots), 'is_dir'));

        $files = [];
        $signature = '';
        foreach ($roots as $root) {
            $finder = (new Finder())->files()->in($root)->exclude(['node_modules', 'vendor', 'public', 'var'])
                ->name(['*.twig', '*.html', '*.tpl'])->size('< 512K')->sortByName();
            foreach ($finder as $file) {
                $files[] = $file->getPathname();
                $signature .= $file->getPathname().'|'.$file->getSize().'|'.$file->getMTime()."\n";
            }
        }

        return $this->cache->get('ccm.theme_hooks.'.hash('sha256', $signature), static function (ItemInterface $item) use ($files): array {
            $item->expiresAfter(86400);

            $source = '';
            foreach ($files as $path) {
                $source .= (string) file_get_contents($path)."\n";
            }

            return array_values(array_filter(self::HOOKS, static fn (string $hook): bool => !str_contains($source, $hook)));
        });
    }
}
