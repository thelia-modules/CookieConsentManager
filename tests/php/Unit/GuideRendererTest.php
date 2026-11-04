<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\GuideRenderer;
use PHPUnit\Framework\TestCase;

final class GuideRendererTest extends TestCase
{
    public function testEverythingIsEscapedAndUnsafeLinksAreDropped(): void
    {
        $html = (new GuideRenderer(__DIR__))->convert("# Titre <b>\n\n<script>alert(1)</script> [x](javascript:alert(1)) [ok](https://example.test/a?b=1&c=2)\n\n```\n<i>code</i>\n```");

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('<a href="https://example.test/a?b=1&amp;c=2"', $html);
        self::assertStringContainsString('&lt;i&gt;code&lt;/i&gt;', $html);
    }

    public function testHeadingsGetStableAnchorsWithoutTheirNumber(): void
    {
        $html = (new GuideRenderer(__DIR__))->convert("## 3. Surcharger un gabarit, pas à pas\n\n- un\n- deux\n\n| a | b |\n|---|---|\n| 1 | 2 |");

        self::assertStringContainsString('<h3 id="surcharger-un-gabarit-pas-a-pas">', $html);
        self::assertStringContainsString('<ul>', $html);
        self::assertStringContainsString('<th>a</th>', $html);
        self::assertStringContainsString('<td>2</td>', $html);
    }

    public function testAMissingGuideRendersNothing(): void
    {
        self::assertNull((new GuideRenderer('/nonexistent'))->render('administrateur'));
        self::assertNull((new GuideRenderer(__DIR__))->render('../etc/passwd'));
    }
}
