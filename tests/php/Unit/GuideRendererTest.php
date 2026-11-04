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

    private static function guides(): GuideRenderer
    {
        return new GuideRenderer(\dirname(__DIR__, 3).'/docs');
    }

    public function testTheLanguageOfTheGuideFollowsTheLocale(): void
    {
        self::assertSame('fr', GuideRenderer::language('fr_FR'));
        self::assertSame('fr', GuideRenderer::language('fr'));
        self::assertSame('en', GuideRenderer::language('en_US'));
        self::assertSame('en', GuideRenderer::language('de_DE'));

        self::assertStringContainsString('Guide administrateur', (string) self::guides()->render('administrateur', 'fr_FR'));
        self::assertStringContainsString('Administrator guide', (string) self::guides()->render('administrateur', 'en_US'));
        self::assertStringContainsString('Integrator guide', (string) self::guides()->render('integrateur', 'es_ES'));
    }

    public function testEveryAnchorTheDashboardLinksToExistsInBothLanguages(): void
    {
        foreach (['template-override' => 'integrateur', 'theme-fonts' => 'administrateur'] as $topic => $guide) {
            foreach (['fr', 'en'] as $locale) {
                self::assertStringContainsString('id="'.GuideRenderer::anchor($topic, $locale).'"', (string) self::guides()->render($guide, $locale), $topic.' in '.$locale);
            }
        }
    }

    public function testALinkToAnotherGuideStaysInTheHelpScreenAndOtherFilesAreNotLinked(): void
    {
        $html = (new GuideRenderer(__DIR__))->convert('See the [integrator guide](integrator-guide.md#overriding-a-template), the [FR guide](guide-administrateur.md), [decision 004](decisions/004-audience-measurement-allowlist.md) and the [README](../README.md#deploying).');

        self::assertStringContainsString('<a href="?guide=integrateur#overriding-a-template">integrator guide</a>', $html);
        self::assertStringContainsString('<a href="?guide=administrateur">FR guide</a>', $html);
        self::assertStringContainsString('decision 004', $html);
        self::assertStringContainsString('the README', $html);
        self::assertSame(2, substr_count($html, '<a '), 'a file that the back-office does not serve gets no dead link');
    }
}
