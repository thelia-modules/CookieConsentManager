<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\MarkupSnippets;
use PHPUnit\Framework\TestCase;

final class MarkupSnippetsTest extends TestCase
{
    private const CATALOGUE = __DIR__.'/../../../Resources/catalogue/catalogue.json';

    public function testTheYoutubeSnippetHasNoPlaceholderWithChoicesLeft(): void
    {
        $snippet = (new MarkupSnippets(self::CATALOGUE))->for('youtube');

        self::assertNotNull($snippet);
        self::assertStringContainsString('data-theme="dark"', $snippet);
        self::assertStringContainsString('data-autoplay="0"', $snippet);
        self::assertStringNotContainsString('|', $snippet);
        self::assertStringNotContainsString('(', $snippet);
        self::assertStringStartsWith('<div class="youtube_player"', $snippet);
    }

    public function testTheTwigExampleDeclaresTheVariablesItUses(): void
    {
        $snippets = new MarkupSnippets(self::CATALOGUE);
        $example = (string) $snippets->twigExample('youtube');

        self::assertStringContainsString('data-videoID="{{ video_id }}"', $example);
        self::assertStringContainsString("{% set video_id = 'VIDEO_ID' %}", $example);
        self::assertStringContainsString('data-theme="dark"', $example, 'a choice is not a variable');
        self::assertStringContainsString("{% set width = '560' %}", $example);
    }

    public function testTheTwigCommentIsTheOneTheCallerTranslated(): void
    {
        $example = (string) (new MarkupSnippets(self::CATALOGUE))->twigExample('youtube', 'Gabarit de votre thème : le bloc reste inerte.');

        self::assertStringStartsWith('{# Gabarit de votre thème : le bloc reste inerte. #}', $example);
    }
}
