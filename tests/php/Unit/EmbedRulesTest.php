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

    /** @return iterable<string, array{string}> */
    public static function writtenDifferentlyButLoadedByTheBrowser(): iterable
    {
        yield 'upper case scheme' => ['HTTPS://www.youtube.com/embed/x'];
        yield 'mixed case scheme' => ['hTtPs://www.youtube.com/embed/x'];
        yield 'upper case host' => ['https://WWW.YouTube.COM/embed/x'];
        yield 'leading space' => [' https://www.youtube.com/embed/x'];
        yield 'leading tab, line feed and form feed' => ["\t\n\x0c https://www.youtube.com/embed/x"];
        yield 'leading control character' => ["\x01https://www.youtube.com/embed/x"];
        yield 'trailing space' => ['https://www.youtube.com/embed/x '];
        yield 'tab inside the scheme' => ["ht\ttps://www.youtube.com/embed/x"];
        yield 'line feed inside the host' => ["https://www.you\ntube.com/embed/x"];
        yield 'carriage return inside the path' => ["https://www.youtube.com/em\rbed/x"];
        yield 'trailing dot on the host' => ['https://www.youtube.com./embed/x'];
        yield 'several trailing dots on the host' => ['https://www.youtube.com.../embed/x'];
        yield 'ideographic full stop' => ["https://www.youtube\u{3002}com/embed/x"];
        yield 'full width letters' => ["https://\u{ff57}\u{ff57}\u{ff57}.youtube.com/embed/x"];
        yield 'backslashes instead of slashes' => ['https:\\\\www.youtube.com\\embed\\x'];
        yield 'one slash only' => ['https:/www.youtube.com/embed/x'];
        yield 'no slash' => ['https:www.youtube.com/embed/x'];
        yield 'three slashes' => ['https:///www.youtube.com/embed/x'];
        yield 'mixed slashes' => ['https:/\\/www.youtube.com/embed/x'];
        yield 'protocol relative with backslashes' => ['\\\\www.youtube.com/embed/x'];
        yield 'protocol relative with a space' => ['  //www.youtube.com/embed/x'];
        yield 'dot segment before the embed path' => ['https://www.youtube.com/x/../embed/y'];
        yield 'dot segment in the middle' => ['https://www.youtube.com/./embed/./y'];
        yield 'encoded dot segment' => ['https://www.youtube.com/x/%2e%2E/embed/y'];
        yield 'dot segment above the root' => ['https://www.youtube.com/../../embed/y'];
        yield 'doubled slash in the path' => ['https://www.youtube.com//embed//y'];
        yield 'encoded letter in the path' => ['https://www.youtube.com/%65mbed/y'];
        yield 'default port' => ['https://www.youtube.com:443/embed/x'];
        yield 'empty port' => ['https://www.youtube.com:/embed/x'];
        yield 'user info before the host' => ['https://user:pass@www.youtube.com/embed/x'];
        yield 'encoded host' => ['https://www.%79outube.com/embed/x'];
        yield 'fragment' => ['https://www.youtube.com/embed/x#a/../b'];
        yield 'invalid port' => ['https://www.youtube.com:evil/embed/x'];
        yield 'forbidden character after the host' => ['https://www.youtube.com^/embed/x'];
        yield 'host between brackets' => ['https://[www.youtube.com]/embed/x'];
        yield 'space inside the host' => ['https://www.youtube .com/embed/x'];
    }

    #[DataProvider('writtenDifferentlyButLoadedByTheBrowser')]
    public function testAnEmbedWrittenDifferentlyIsStillRecognised(string $src): void
    {
        self::assertSame('youtube', EmbedRules::serviceFor($src, ['youtube']), json_encode($src));
    }

    /** @return iterable<string, array{string}> */
    public static function notTheService(): iterable
    {
        yield 'lookalike host' => ['https://www.youtube.com.evil.test/embed/x'];
        yield 'lookalike host with a trailing dot' => ['https://www.youtube.com.evil.test./embed/x'];
        yield 'service host inside the path' => ['https://evil.test/www.youtube.com/embed/x'];
        yield 'service host inside the query' => ['https://evil.test/?u=https://www.youtube.com/embed/x'];
        yield 'service host as user info' => ['https://www.youtube.com@evil.test/embed/x'];
        yield 'dot segments leaving the embed path' => ['https://www.youtube.com/embed/../watch?v=x'];
        yield 'encoded dot segments leaving the embed path' => ['https://www.youtube.com/embed/%2e%2e/watch'];
        yield 'backslash ends the authority, the rest is a path' => ['https://www.youtube.com\\@evil.test/embed/x'];
        yield 'relative path' => ['/www.youtube.com/embed/x'];
        yield 'relative host name' => ['www.youtube.com/embed/x'];
        yield 'other scheme' => ['ftp://www.youtube.com/embed/x'];
        yield 'javascript scheme naming the host' => ['javascript:void(0)//www.youtube.com/embed/x'];
        yield 'empty' => [''];
        yield 'unrelated host that cannot be parsed' => ['https://exa mple.test^/embed/x'];
    }

    #[DataProvider('notTheService')]
    public function testWhatIsNotTheServiceStaysUntouched(string $src): void
    {
        self::assertNull(EmbedRules::serviceFor($src, ['youtube']), json_encode($src));
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
