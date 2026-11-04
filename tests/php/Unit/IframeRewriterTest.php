<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Front\IframeRewriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IframeRewriterTest extends TestCase
{
    private const ALL = ['youtube', 'vimeo', 'dailymotion', 'maps_noapi'];

    /**
     * @param list<string> $active
     *
     * @return array{string, list<array{string, string, array<string, string>}>}
     */
    private function rewriteOne(string $html, array $active = self::ALL): array
    {
        $seen = [];
        $out = (new IframeRewriter())->rewrite($html, $active, static function (string $key, string $src, array $attributes) use (&$seen): string {
            $seen[] = [$key, $src, $attributes];

            return '[PH:'.$key.']';
        });

        return [$out, $seen];
    }

    public function testReplacesAYoutubeIframeAndKeepsItsOtherAttributes(): void
    {
        [$out, $seen] = $this->rewriteOne('<p>a</p><iframe width="560" height="315" src="https://www.youtube.com/embed/abc?rel=0" title="Video &amp; co" allowfullscreen></iframe><p>b</p>');

        self::assertSame('<p>a</p>[PH:youtube]<p>b</p>', $out);
        self::assertSame([['youtube', 'https://www.youtube.com/embed/abc?rel=0', ['width' => '560', 'height' => '315', 'title' => 'Video & co', 'allowfullscreen' => '']]], $seen);
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function sources(): iterable
    {
        yield 'single quotes' => ["<iframe src='https://player.vimeo.com/video/1'></iframe>", 'vimeo'];
        yield 'no quotes' => ['<iframe src=https://www.dailymotion.com/embed/video/x1></iframe>', 'dailymotion'];
        yield 'protocol relative' => ['<iframe src="//www.youtube-nocookie.com/embed/x"></iframe>', 'youtube'];
        yield 'upper case tag and attribute' => ['<IFRAME SRC="https://www.youtube.com/embed/x"></IFRAME>', 'youtube'];
        yield 'maps embed' => ['<iframe src="https://www.google.com/maps/embed?pb=!1m18"></iframe>', 'maps_noapi'];
        yield 'greater than inside a quoted attribute' => ['<iframe title="a > b" src="https://www.youtube.com/embed/x"></iframe>', 'youtube'];
        yield 'no closing tag' => ['<iframe src="https://www.youtube.com/embed/x">', 'youtube'];
        yield 'unrelated domain' => ['<iframe src="https://example.com/embed/x"></iframe>', null];
        yield 'recaptcha frame on google.com' => ['<iframe src="https://www.google.com/recaptcha/api2/anchor"></iframe>', null];
        yield 'youtube but not an embed path' => ['<iframe src="https://www.youtube.com/watch?v=x"></iframe>', null];
        yield 'lookalike host' => ['<iframe src="https://www.youtube.com.evil.test/embed/x"></iframe>', null];
        yield 'no src' => ['<iframe srcdoc="x"></iframe>', null];
        yield 'javascript scheme' => ['<iframe src="javascript:alert(1)"></iframe>', null];
    }

    #[DataProvider('sources')]
    public function testWhichIframesAreReplaced(string $html, ?string $service): void
    {
        [$out, $seen] = $this->rewriteOne($html);

        if (null === $service) {
            self::assertSame($html, $out);
            self::assertSame([], $seen);

            return;
        }

        self::assertSame('[PH:'.$service.']', $out);
    }

    public function testSeveralIframesEachDecidedOnItsOwn(): void
    {
        [$out, $seen] = $this->rewriteOne('<iframe src="https://www.youtube.com/embed/1"></iframe><iframe src="https://example.com/x"></iframe><iframe src="https://player.vimeo.com/video/2"></iframe>');

        self::assertSame('[PH:youtube]<iframe src="https://example.com/x"></iframe>[PH:vimeo]', $out);
        self::assertCount(2, $seen);
    }

    public function testAnIframeWithoutClosingTagDoesNotSwallowTheNextOne(): void
    {
        [$out] = $this->rewriteOne('<iframe src="https://www.youtube.com/embed/1"><iframe src="https://example.com/x"></iframe>');

        self::assertSame('[PH:youtube]<iframe src="https://example.com/x"></iframe>', $out);
    }

    public function testAServiceThatIsNotActiveLeavesItsIframe(): void
    {
        $html = '<iframe src="https://www.youtube.com/embed/1"></iframe><iframe src="https://player.vimeo.com/video/2"></iframe>';
        [$out] = $this->rewriteOne($html, ['vimeo']);

        self::assertSame('<iframe src="https://www.youtube.com/embed/1"></iframe>[PH:vimeo]', $out);
        self::assertSame($html, $this->rewriteOne($html, [])[0]);
    }

    public function testScriptsTemplatesStylesTextareasAndCommentsAreNeverTouched(): void
    {
        $html = '<script>var f = \'<iframe src="https://www.youtube.com/embed/1"></iframe>\';</script>'
            .'<template><iframe src="https://www.youtube.com/embed/2"></iframe></template>'
            .'<!-- <iframe src="https://www.youtube.com/embed/3"></iframe> -->'
            .'<textarea><iframe src="https://www.youtube.com/embed/4"></iframe></textarea>'
            .'<iframe src="https://www.youtube.com/embed/5"></iframe>';

        [$out, $seen] = $this->rewriteOne($html);

        self::assertSame(substr($html, 0, -\strlen('<iframe src="https://www.youtube.com/embed/5"></iframe>')).'[PH:youtube]', $out);
        self::assertCount(1, $seen);
        self::assertSame('https://www.youtube.com/embed/5', $seen[0][1]);
    }

    public function testTheRestOfThePageIsByteForByteUntouched(): void
    {
        $html = "<!doctype html>\n<html><head><title>é &amp; à</title></head>\n<body class=\"x\">\t<p>\u{e9}</p></body></html>";

        self::assertSame($html, $this->rewriteOne($html)[0]);
    }
}
