<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Front\OverrideScanner;
use CookieConsentManager\Publication\ModuleVersion;
use PHPUnit\Framework\TestCase;

final class OverrideSignatureTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/ccm-signature-'.bin2hex(random_bytes(4));
        mkdir($this->root.'/child/CookieConsentManager', 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/child/CookieConsentManager/*') ?: [] as $file) {
            chmod($file, 0o644);
            unlink($file);
        }
        foreach ([$this->root.'/child/CookieConsentManager', $this->root.'/child', $this->root] as $dir) {
            @rmdir($dir);
        }
    }

    public function testTheSignatureChangesWithAnyOverrideEditAdditionOrRemoval(): void
    {
        $scanner = new OverrideScanner();
        $themes = ['child' => $this->root.'/child'];
        $banner = $this->root.'/child/CookieConsentManager/banner.html.twig';

        $empty = $scanner->signature($themes);
        file_put_contents($banner, 'one');
        touch($banner, 1_700_000_000);
        $added = $scanner->signature($themes);
        self::assertNotSame($empty, $added, 'an override added');
        self::assertSame($added, $scanner->signature($themes), 'stable while nothing changes');

        file_put_contents($banner, 'one');
        touch($banner, 1_700_000_100);
        $touched = $scanner->signature($themes);
        self::assertNotSame($added, $touched, 'same content, later modification time');

        file_put_contents($banner, 'longer content');
        touch($banner, 1_700_000_100);
        self::assertNotSame($touched, $scanner->signature($themes), 'same modification time, other size');

        unlink($banner);
        self::assertSame($empty, $scanner->signature($themes), 'an override removed');
    }

    public function testTheSignatureDoesNotReadTheTemplates(): void
    {
        $banner = $this->root.'/child/CookieConsentManager/banner.html.twig';
        file_put_contents($banner, 'secret');
        chmod($banner, 0o000);

        self::assertNotSame('', (new OverrideScanner())->signature(['child' => $this->root.'/child']));
    }

    public function testTheModuleVersionIsReadAgainOnlyOnceTheDescriptorChanged(): void
    {
        $descriptor = $this->root.'/module.xml';
        file_put_contents($descriptor, '<module><version>1.0.0</version></module>');
        touch($descriptor, 1_700_000_000);
        $version = new ModuleVersion($descriptor);
        self::assertSame('1.0.0', $version->current());

        file_put_contents($descriptor, '<module><version>1.0.1</version></module>');
        touch($descriptor, 1_700_000_000);
        self::assertSame('1.0.0', $version->current(), 'same modification time: not parsed again');

        touch($descriptor, 1_700_000_100);
        self::assertSame('1.0.1', $version->current(), 'an update of the module is seen by a long-lived worker');
        unlink($descriptor);
    }
}
