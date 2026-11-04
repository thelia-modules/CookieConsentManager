<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

/**
 * The embeddable iframes of the verified media services. A host alone is not enough: google.com also
 * serves reCAPTCHA frames, so each rule carries the path prefixes of the embed endpoints.
 */
final class EmbedRules
{
    /** @var array<string, array<string, list<string>>> service key => host => path prefixes */
    public const RULES = [
        'youtube' => [
            'www.youtube.com' => ['/embed/'],
            'youtube.com' => ['/embed/'],
            'www.youtube-nocookie.com' => ['/embed/'],
            'youtube-nocookie.com' => ['/embed/'],
        ],
        'vimeo' => ['player.vimeo.com' => ['/video/']],
        'dailymotion' => [
            'www.dailymotion.com' => ['/embed/'],
            'geo.dailymotion.com' => ['/player'],
        ],
        'maps_noapi' => [
            'www.google.com' => ['/maps/embed', '/maps?'],
            'maps.google.com' => ['/maps'],
        ],
    ];

    /** @param list<string> $activeKeys keys of the media services active and published */
    public static function serviceFor(string $src, array $activeKeys): ?string
    {
        $parts = parse_url(str_starts_with($src, '//') ? 'https:'.$src : $src);
        if (!\is_array($parts) || !isset($parts['host']) || !\in_array($parts['scheme'] ?? 'https', ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host']);
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        foreach (self::RULES as $key => $hosts) {
            if (!\in_array($key, $activeKeys, true) || !isset($hosts[$host])) {
                continue;
            }
            foreach ($hosts[$host] as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    return $key;
                }
            }
        }

        return null;
    }
}
