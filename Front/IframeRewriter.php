<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Replaces the iframes of the media services by their placeholder (SPEC 5.4). It works on the markup
 * as text, not through a DOM parser, so that the rest of the page stays byte for byte what the theme
 * produced; scripts, styles, templates, textareas and comments are never touched.
 */
final class IframeRewriter
{
    private const PROTECTED = '~(<!--.*?-->|<script\b.*?</script\s*>|<template\b.*?</template\s*>|<style\b.*?</style\s*>|<textarea\b.*?</textarea\s*>)~is';
    private const IFRAME = '~<iframe\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>((?:(?!<iframe\b).)*?)(?:</iframe\s*>|(?=<iframe\b)|\z)~is';
    private const ATTRIBUTE = '~([^\s"\'<>/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?~';

    public function __construct(private readonly LoggerInterface $logger = new NullLogger())
    {
    }

    /**
     * @param list<string>                                                                  $activeKeys
     * @param callable(string $key, string $src, array<string, string> $attributes): string $placeholder
     */
    public function rewrite(string $html, array $activeKeys, callable $placeholder): string
    {
        if ([] === $activeKeys || false === stripos($html, '<iframe')) {
            return $html;
        }

        $segments = preg_split(self::PROTECTED, $html, -1, \PREG_SPLIT_DELIM_CAPTURE);
        if (false === $segments) {
            $this->logger->warning('Iframe rewriting skipped: the page could not be split ({error}).', ['error' => preg_last_error_msg()]);

            return $html;
        }

        foreach ($segments as $index => $segment) {
            if (0 === $index % 2 && false !== stripos($segment, '<iframe')) {
                $rewritten = preg_replace_callback(self::IFRAME, function (array $match) use ($activeKeys, $placeholder): string {
                    $attributes = $this->attributes($match[1]);
                    $src = $attributes['src'] ?? '';
                    $key = '' === $src ? null : EmbedRules::serviceFor($src, $activeKeys);
                    if (null === $key) {
                        return $match[0];
                    }
                    unset($attributes['src']);

                    return $placeholder($key, $src, $attributes);
                }, $segment);
                if (null === $rewritten) {
                    // A PCRE failure (backtrack limit) must never blank the page: the original markup is served.
                    $this->logger->warning('Iframe rewriting skipped: the page could not be processed ({error}).', ['error' => preg_last_error_msg()]);

                    return $html;
                }
                $segments[$index] = $rewritten;
            }
        }

        return implode('', $segments);
    }

    /** @return array<string, string> attribute names in lower case, values decoded */
    private function attributes(string $raw): array
    {
        preg_match_all(self::ATTRIBUTE, $raw, $found, \PREG_SET_ORDER);
        $attributes = [];
        foreach ($found as $attribute) {
            $name = strtolower($attribute[1]);
            $value = $attribute[4] ?? ($attribute[3] ?? ($attribute[2] ?? ''));
            $attributes[$name] ??= html_entity_decode($value, \ENT_QUOTES | \ENT_HTML5);
        }

        return $attributes;
    }
}
