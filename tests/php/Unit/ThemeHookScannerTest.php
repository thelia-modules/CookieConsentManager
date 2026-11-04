<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\ThemeHookScanner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;

final class ThemeHookScannerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/ccm-theme-'.bin2hex(random_bytes(4));
        mkdir($this->root.'/child', 0o777, true);
        mkdir($this->root.'/parent', 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/*/*') ?: [] as $file) {
            unlink($file);
        }
        foreach ([$this->root.'/child', $this->root.'/parent', $this->root] as $dir) {
            @rmdir($dir);
        }
    }

    private function scanner(bool $withParent = false): ThemeHookScanner
    {
        $parent = $this->createStub(TemplateDefinition::class);
        $parent->method('getAbsolutePath')->willReturn($this->root.'/parent');
        $theme = $this->createStub(TemplateDefinition::class);
        $theme->method('getAbsolutePath')->willReturn($this->root.'/child');
        $theme->method('getParentList')->willReturn($withParent ? [$parent] : null);
        $helper = $this->createStub(TemplateHelperInterface::class);
        $helper->method('getActiveFrontTemplate')->willReturn($theme);

        return new ThemeHookScanner($helper, new ArrayAdapter());
    }

    public function testAThemeCallingNeitherHookIsReportedForBoth(): void
    {
        file_put_contents($this->root.'/child/base.html.twig', '<html>{{ theme_hook("main.head-bottom") }}</html>');

        self::assertSame(['layout.head.top', 'layout.body.top'], $this->scanner()->missing());
    }

    public function testOnlyTheMissingHookIsReportedAndNothingWhenBothAreCalled(): void
    {
        file_put_contents($this->root.'/child/base.html.twig', "{{ theme_hook('layout.head.top') }}");
        self::assertSame(['layout.body.top'], $this->scanner()->missing());

        file_put_contents($this->root.'/child/footer.html.twig', "{{ theme_hook('layout.body.top') }}");
        self::assertSame([], $this->scanner()->missing(), 'the cache follows the files');
    }

    public function testTheHooksOfAParentThemeCount(): void
    {
        file_put_contents($this->root.'/child/page.html.twig', 'child');
        file_put_contents($this->root.'/parent/base.html.twig', "{{ theme_hook('layout.head.top') }}{{ theme_hook('layout.body.top') }}");

        self::assertSame([], $this->scanner(true)->missing());
        self::assertSame(['layout.head.top', 'layout.body.top'], $this->scanner(false)->missing());
    }
}
