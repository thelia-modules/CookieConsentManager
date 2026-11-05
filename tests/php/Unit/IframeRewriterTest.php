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

    /** @return iterable<string, array{string}> */
    public static function iframesThatAreNotTagsOfThePage(): iterable
    {
        $embed = "<iframe src='https://www.youtube.com/embed/a'></iframe>";
        yield 'double quoted attribute' => ['<a title="'.$embed.'<img src=x onerror=alert(1)>">link</a>'];
        yield 'single quoted attribute' => ["<a title='".str_replace("'", '"', $embed).'<img src=x onerror=alert(1)>\'>link</a>'];
        yield 'attribute of an iframe that is not rewritten' => ['<iframe title="'.$embed.'" src="https://example.com/x"></iframe>'];
        yield 'attribute of a closing tag' => ['<p></p title="'.$embed.'">'];
        yield 'attribute of a self closing tag' => ['<img alt="'.$embed.'<b>" />'];
        yield 'unquoted value' => ['<a title=<iframe src=https://www.youtube.com/embed/a>link</a>'];
        yield 'several attributes before the one that holds it' => ['<a href="/x" class=y data-a=\'1\' title="'.$embed.'">link</a>'];
        yield 'attribute without value, then spaces around the equal sign' => ['<a hidden title  =  "'.$embed.'">link</a>'];
        yield 'greater than inside the quoted value' => ['<a title="> '.$embed.'">link</a>'];
        yield 'unclosed comment' => ['<p>a</p><!-- '.$embed];
        yield 'bogus comment' => ['<!x '.$embed.' >'];
        yield 'cdata' => ['<![CDATA[ '.$embed.' ]]>'];
        yield 'unclosed script' => ['<script>var a = 1;'.$embed];
        yield 'plaintext' => ['<plaintext>'.$embed];
        yield 'title and xmp' => ['<title>'.$embed.'</title><xmp>'.$embed.'</xmp>'];
        yield 'tag not terminated at the end of the page' => ['<p>a</p><iframe src="https://www.youtube.com/embed/a"'];
        yield 'custom element named like an iframe' => ['<iframe-lazy src="https://www.youtube.com/embed/a"></iframe-lazy>'];
        yield 'duplicated src: the first one wins' => ['<iframe src="https://example.com/x" src="https://www.youtube.com/embed/a"></iframe>'];
    }

    #[DataProvider('iframesThatAreNotTagsOfThePage')]
    public function testAnIframeThatIsNotATagOfThePageIsNeverRewritten(string $html): void
    {
        [$out, $seen] = $this->rewriteOne($html);

        self::assertSame($html, $out);
        self::assertSame([], $seen);
    }

    /** @return iterable<string, array{string, string}> */
    public static function iframesTheBrowserReallyLoads(): iterable
    {
        $embed = '<iframe src="https://www.youtube.com/embed/a"></iframe>';
        yield 'quote inside an unquoted value does not open a string' => ['<a b=x">'.$embed.'">', '<a b=x">[PH:youtube]">'];
        yield 'after a tag whose quoted value holds a greater than' => ['<a title="a > b">'.$embed, '<a title="a > b">[PH:youtube]'];
        yield 'slash instead of a space before src' => ['<iframe/src="https://www.youtube.com/embed/a"></iframe>', '[PH:youtube]'];
        yield 'script tag inside an attribute of the iframe' => ['<iframe title="<script>x</script>" src="https://www.youtube.com/embed/a"></iframe>', '[PH:youtube]'];
        yield 'script end tag inside an attribute of the iframe' => ['<iframe data-x=\'</script>\' src="https://www.youtube.com/embed/a"></iframe>', '[PH:youtube]'];
        yield 'comment start inside an attribute of the iframe' => ['<iframe title="<!--" src="https://www.youtube.com/embed/a"></iframe>', '[PH:youtube]'];
        yield 'template start inside an attribute of the iframe' => ['<iframe title="<template>" src="https://www.youtube.com/embed/a"></iframe>', '[PH:youtube]'];
        yield 'empty comment' => ['<!-->'.$embed, '<!-->[PH:youtube]'];
        yield 'empty comment with a dash' => ['<!--->'.$embed, '<!--->[PH:youtube]'];
        yield 'comment closed by an exclamation mark' => ['<!-- a --!>'.$embed, '<!-- a --!>[PH:youtube]'];
        yield 'iframe after a raw text element' => ['<script>"<iframe";</script>'.$embed, '<script>"<iframe";</script>[PH:youtube]'];
        yield 'script end tag that is not one' => ['<script>1</scripts>'.$embed.'</script>'.$embed, '<script>1</scripts>'.$embed.'</script>[PH:youtube]'];
        yield 'noscript read as markup by a browser without scripts' => ['<noscript>'.$embed.'</noscript>', '<noscript>[PH:youtube]</noscript>'];
        yield 'noscript read as text by a browser with scripts' => ['<noscript><p title="</noscript>'.$embed.'">', '<noscript><p title="</noscript>[PH:youtube]">'];
        yield 'style inside a foreign object' => ['<svg><foreignObject><style><a title="</style>'.$embed.'"></style></foreignObject></svg>', '<svg><foreignObject><style><a title="</style>[PH:youtube]"></style></foreignObject></svg>'];
        yield 'style left by a breakout element after a nested svg' => ['<svg><svg></svg><style><p>'.$embed.'</style></svg>', '<svg><svg></svg><style><p>[PH:youtube]</style></svg>'];
        yield 'iframe in the svg that follows a closed svg is a normal page again' => ['<svg></svg><style>'.$embed.'</style>'.$embed, '<svg></svg><style>'.$embed.'</style>[PH:youtube]'];
        yield 'numeric reference without semicolon in the scheme' => ['<iframe src="&#104ttps://www.youtube.com/embed/a"></iframe>', '[PH:youtube]'];
        yield 'hexadecimal reference in the scheme' => ['<iframe src="&#x68;ttps://www.youtube.com/embed/a"></iframe>', '[PH:youtube]'];
        yield 'named references for tab and line feed' => ['<iframe src="https://www.you&Tab;tube.com/embed&NewLine;/a"></iframe>', '[PH:youtube]'];
        yield 'upper case scheme and trailing dot' => ['<iframe src="HTTPS://www.youtube.com./embed/a"></iframe>', '[PH:youtube]'];
        yield 'tab and space around the address' => ["<iframe src=\"\t https://www.youtube.com/embed/a \"></iframe>", '[PH:youtube]'];
        yield 'dot segments' => ['<iframe src="https://www.youtube.com/x/../embed/a"></iframe>', '[PH:youtube]'];
    }

    #[DataProvider('iframesTheBrowserReallyLoads')]
    public function testAnIframeTheBrowserReallyLoadsIsRewritten(string $html, string $expected): void
    {
        self::assertSame($expected, $this->rewriteOne($html)[0]);
    }

    public function testAReferenceIsDecodedOnlyOnce(): void
    {
        $html = '<iframe src="&amp;#104;ttps://www.youtube.com/embed/a"></iframe>';

        self::assertSame($html, $this->rewriteOne($html)[0]);
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
