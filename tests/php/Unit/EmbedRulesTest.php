<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Front\EmbedRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmbedRulesTest extends TestCase
{
    private const ROOT = __DIR__.'/../../..';

    /** @return list<string> */
    private static function mediaKeys(): array
    {
        $catalogue = json_decode((string) file_get_contents(self::ROOT.'/Resources/catalogue/catalogue.json'), true, 512, \JSON_THROW_ON_ERROR);

        return array_column(array_filter($catalogue, static fn (array $row): bool => 'media' === $row['kind']), 'key');
    }

    /** @return iterable<string, array{string, string}> */
    public static function embeds(): iterable
    {
        yield 'spotify' => ['spotify', 'https://open.spotify.com/embed/track/abc'];
        yield 'deezer' => ['deezer', '//widget.deezer.com/widget/dark/track/123?x=1'];
        yield 'google docs' => ['gdocs', 'https://docs.google.com/document/d/e/2PACX/pub?embedded=true'];
        yield 'google forms' => ['gforms', 'https://docs.google.com/forms/d/e/1FAIpQ/viewform?embedded=true'];
        yield 'google calendar' => ['gagenda', 'https://www.google.com/calendar/embed?src=x'];
        yield 'calameo' => ['calameo', 'https://v.calameo.com/?bkcode=1'];
        yield 'instagram' => ['instagram', 'https://www.instagram.com/p/abc/embed'];
        yield 'youtube playlist' => ['youtubeplaylist', 'https://www.youtube-nocookie.com/embed/videoseries?list=PL1'];
    }

    #[DataProvider('embeds')]
    public function testTheEmbedOfAnActiveServiceIsRecognised(string $key, string $src): void
    {
        self::assertSame($key, EmbedRules::serviceFor($src, [$key]));
        self::assertNull(EmbedRules::serviceFor($src, ['vimeo']), 'a service that is not active never matches');
    }

    public function testASharedHostIsNotAClaimForEveryPage(): void
    {
        $all = array_keys(EmbedRules::rules());

        self::assertNull(EmbedRules::serviceFor('https://www.google.com/recaptcha/api2/anchor?k=x', $all), 'reCAPTCHA frames are not media');
        self::assertNull(EmbedRules::serviceFor('https://docs.google.com/spreadsheets/d/abc/edit', ['gdocs']), 'a Docs rule does not claim Sheets');
        self::assertNull(EmbedRules::serviceFor('javascript:alert(1)', $all));
    }

    public function testTheMostSpecificRuleWins(): void
    {
        $playlist = 'https://www.youtube-nocookie.com/embed/videoseries?list=PL1';
        $video = 'https://www.youtube-nocookie.com/embed/abc';

        self::assertSame('youtubeplaylist', EmbedRules::serviceFor($playlist, ['youtube', 'youtubeplaylist']));
        self::assertSame('youtube', EmbedRules::serviceFor($video, ['youtube', 'youtubeplaylist']));
        self::assertSame('youtube', EmbedRules::serviceFor($playlist, ['youtube']));
    }

    public function testEveryMediaServiceIsEitherCoveredOrDocumentedAsNot(): void
    {
        $derived = json_decode((string) file_get_contents(self::ROOT.'/Resources/catalogue/embed-rules.json'), true, 512, \JSON_THROW_ON_ERROR);
        $covered = array_keys(EmbedRules::rules());
        $uncovered = array_keys($derived['undetectable']);
        $media = self::mediaKeys();

        self::assertSame([], array_values(array_diff($covered, $media)), 'a rule targets a service that is not a media service');
        self::assertSame([], array_values(array_diff($media, $covered, array_diff($uncovered, $covered))), 'a media service is neither covered nor explained');
        self::assertGreaterThanOrEqual(40, \count($covered));

        foreach (['docs/integrator-guide.md', 'docs/fr/guide-integrateur.md'] as $guide) {
            $text = (string) file_get_contents(self::ROOT.'/'.$guide);
            foreach (array_diff($uncovered, $covered) as $key) {
                self::assertStringContainsString('`'.$key.'`', $text, $key.' is not listed as uncovered in '.$guide);
            }
        }
    }
}
