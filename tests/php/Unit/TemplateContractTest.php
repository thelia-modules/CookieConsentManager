<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Front\OverrideScanner;
use CookieConsentManager\Front\TemplateContext;
use CookieConsentManager\Front\TemplateContractChecker;
use CookieConsentManager\Publication\OverriddenTemplatesProvider;
use PHPUnit\Framework\TestCase;

final class TemplateContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/ccm-contract-'.bin2hex(random_bytes(4));
        foreach (['child', 'parent'] as $theme) {
            mkdir($this->root.'/'.$theme.'/CookieConsentManager', 0o777, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/*/CookieConsentManager/*') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->root.'/*/CookieConsentManager') ?: [] as $dir) {
            rmdir($dir);
        }
        foreach (glob($this->root.'/*') ?: [] as $dir) {
            rmdir($dir);
        }
        rmdir($this->root);
    }

    private function put(string $theme, string $file, string $content): void
    {
        file_put_contents($this->root.'/'.$theme.'/CookieConsentManager/'.$file, $content);
    }

    /** @return array<string, string> */
    private function themes(): array
    {
        return ['child' => $this->root.'/child', 'parent' => $this->root.'/parent'];
    }

    public function testTheMarkerIsReadFromTheFirstLine(): void
    {
        self::assertSame('1', OverrideScanner::contract("{# ccm-templates:1 #}\n<div></div>"));
        self::assertSame('2', OverrideScanner::contract("  {#ccm-templates:2#}\nx"));
        self::assertNull(OverrideScanner::contract("<div></div>\n{# ccm-templates:1 #}"), 'only the first line counts');
        self::assertNull(OverrideScanner::contract('{# some other comment #}'));
    }

    public function testEveryOverrideReportsItsVersionAndTheClosestThemeWins(): void
    {
        $this->put('child', 'banner.html.twig', "{# ccm-templates:1 #}\nchild");
        $this->put('child', 'placeholder.html.twig', 'no marker');
        $this->put('parent', 'banner.html.twig', "{# ccm-templates:9 #}\nparent");
        $this->put('parent', 'open-link.html.twig', "{# ccm-templates:2 #}\n");

        $list = (new OverrideScanner())->scan($this->themes());

        self::assertSame(['banner.html.twig', 'open-link.html.twig', 'placeholder.html.twig'], array_column($list, 'template'));
        self::assertSame(['1', '2', null], array_column($list, 'contract'));
        self::assertSame(['child', 'parent', 'child'], array_column($list, 'source'));
    }

    public function testTheCheckerListsTheOverridesWithoutMarkerOrWithAnotherVersion(): void
    {
        $this->put('child', 'banner.html.twig', '{# ccm-templates:'.TemplateContext::CONTRACT_VERSION." #}\nok");
        $this->put('child', 'placeholder.html.twig', 'no marker');
        $this->put('child', 'open-link.html.twig', "{# ccm-templates:0 #}\n");

        $scanner = new OverrideScanner();
        $provider = new class($scanner, $this->themes()) implements OverriddenTemplatesProvider {
            /** @param array<string, string> $themes */
            public function __construct(private readonly OverrideScanner $scanner, private readonly array $themes)
            {
            }

            public function list(): array
            {
                return $this->scanner->scan($this->themes);
            }
        };

        $outdated = (new TemplateContractChecker($provider))->outdated();

        self::assertSame(['open-link.html.twig', 'placeholder.html.twig'], array_column($outdated, 'template'));
        self::assertSame(['0', null], array_column($outdated, 'contract'));
    }

    public function testEveryDeliveredTemplateCarriesTheCurrentMarkerOnItsFirstLine(): void
    {
        $files = glob(\dirname(__DIR__, 3).'/templates/frontOffice/*/CookieConsentManager/*.twig') ?: [];

        self::assertCount(14, $files);
        foreach ($files as $file) {
            self::assertSame(TemplateContext::CONTRACT_VERSION, OverrideScanner::contract((string) file_get_contents($file)), $file);
        }
    }
}
