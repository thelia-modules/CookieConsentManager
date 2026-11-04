<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

final class GuardTemplateTest extends TestCase
{
    private const PROBE = "{% set probe %}{% include 'CookieConsentManager/guard.html.twig' ignore missing %}{% endset %}{{ probe|trim }}";

    /** @return iterable<string, array{string}> */
    public static function themes(): iterable
    {
        yield 'default' => ['default'];
        yield 'flexy' => ['flexy'];
    }

    #[DataProvider('themes')]
    public function testTheProbeAnswersWhileTheModuleTemplatesAreResolved(string $theme): void
    {
        self::assertSame('1', $this->render(\dirname(__DIR__, 3).'/templates/frontOffice/'.$theme));
    }

    public function testTheProbeIsEmptyOnceTheModuleTemplatesAreGone(): void
    {
        self::assertSame('', $this->render(sys_get_temp_dir()));
    }

    private function render(string $templates): string
    {
        $twig = new Environment(new ChainLoader([
            new ArrayLoader(['page' => self::PROBE]),
            new FilesystemLoader([$templates]),
        ]));

        return $twig->render('page');
    }
}
