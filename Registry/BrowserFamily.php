<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

final class BrowserFamily
{
    private const BOT = '/bot|crawl|spider|slurp|lighthouse|pingdom|monitor|curl|wget|python-requests|httpclient|facebookexternalhit|preview/i';

    /** @return array{label: string, bot: bool} family and major version only, never the full user agent */
    public static function parse(?string $userAgent): array
    {
        $userAgent ??= '';
        $bot = '' === $userAgent || 1 === preg_match(self::BOT, $userAgent);

        $families = [
            'Edge' => '~Edg(?:e|A|iOS)?/(\d+)~',
            'Opera' => '~(?:OPR|Opera)/(\d+)~',
            'Firefox' => '~(?:Firefox|FxiOS)/(\d+)~',
            'Chrome' => '~(?:Chrome|CriOS)/(\d+)~',
            'Safari' => '~Version/(\d+)[^ ]* (?:Mobile/\S+ )?Safari/~',
        ];

        foreach ($families as $family => $pattern) {
            if (1 === preg_match($pattern, $userAgent, $matches)) {
                return ['label' => $family.' '.$matches[1], 'bot' => $bot];
            }
        }

        return ['label' => 'Other', 'bot' => $bot];
    }
}
