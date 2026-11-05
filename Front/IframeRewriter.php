<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Replaces the iframes of the media services by their placeholder, only when the service is active and published. It reads
 * the markup as text, with the tokenizer rules of a browser (tags and their quoted attributes, comments, raw text elements)
 * and without building a DOM, so that the rest of the page stays byte for byte what the theme produced. Only an iframe that
 * is a tag of the page is replaced: never one that sits in an attribute value, a comment, a script, a style, a template or
 * a textarea.
 *
 * Where a browser could read the same bytes two ways (a noscript, the content of an svg), both readings are applied, so
 * that an iframe one of them loads is never skipped.
 */
final class IframeRewriter
{
    private const SPACE = " \t\n\f\r";

    /** Elements whose content is text to a browser: nothing in it is a tag. */
    private const RAW_TEXT = ['script' => true, 'style' => true, 'textarea' => true, 'title' => true, 'xmp' => true, 'noembed' => true, 'noframes' => true];

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

        try {
            return $this->scan($html, $activeKeys, $placeholder);
        } catch (\RuntimeException $exception) {
            // Never serve the original markup: its iframes would load without any consent.
            $this->logger->warning('Iframe rewriting failed, the frames of the media services are emptied instead ({error}).', ['error' => $exception->getMessage()]);

            return $this->neutralise($html, $activeKeys);
        }
    }

    /**
     * The fallback when the page cannot be read: no tokenizer, no pattern, so nothing left to fail. Every iframe that names a
     * host of an active service, or hides its address behind character references, gets an empty document and a blank
     * address in front of its attributes (a browser keeps the first of two identical attributes). Nothing is added that could
     * close a quoted attribute, so that an iframe inside an attribute value stays harmless.
     *
     * @param list<string> $activeKeys
     */
    private function neutralise(string $html, array $activeKeys): string
    {
        $hosts = [];
        foreach (EmbedRules::rules() as $key => $byHost) {
            if (\in_array($key, $activeKeys, true)) {
                array_push($hosts, ...array_keys($byHost));
            }
        }

        $out = '';
        $copied = 0;
        $offset = 0;
        while (false !== ($at = stripos($html, '<iframe', $offset))) {
            $offset = $at + 7;
            $window = strtolower(str_replace(self::SPACE, '', substr($html, $at, 4096)));
            $names = str_contains($window, '&#') || str_contains($window, '&tab;') || str_contains($window, '&newline;');
            foreach ($hosts as $host) {
                $names = $names || str_contains($window, $host);
            }
            if ($names) {
                $out .= substr($html, $copied, $offset - $copied).' srcdoc=&#32; src=about:blank ';
                $copied = $offset;
            }
        }

        return $out.substr($html, $copied);
    }

    /**
     * @param list<string>                                                                  $activeKeys
     * @param callable(string $key, string $src, array<string, string> $attributes): string $placeholder
     */
    private function scan(string $html, array $activeKeys, callable $placeholder): string
    {
        $length = \strlen($html);
        $edits = [];
        $ends = [];
        $foreign = ['svg' => 0, 'math' => 0];
        $position = 0;

        while ($position < $length && false !== ($start = strpos($html, '<', $position))) {
            $position = $start + 1;
            $next = $html[$position] ?? '';
            $isEnd = '/' === $next;
            $nameStart = $position + ($isEnd ? 1 : 0);

            if (!ctype_alpha($html[$nameStart] ?? '')) {
                if ('!' === $next || '?' === $next || $isEnd) {
                    $position = $this->skipNonTag($html, $start);
                }
                continue;
            }

            $nameLength = strcspn($html, self::SPACE.'/>', $nameStart);
            $name = strtolower(substr($html, $nameStart, $nameLength));
            $isIframe = !$isEnd && 'iframe' === $name;
            [$tagEnd, $attributes, $selfClosing] = $this->tag($html, $nameStart + $nameLength, $isIframe);
            if (null === $tagEnd) {
                break;
            }
            $position = $tagEnd;

            if (isset($foreign[$name])) {
                $foreign[$name] = $isEnd ? max(0, $foreign[$name] - 1) : $foreign[$name] + ($selfClosing ? 0 : 1);
            } elseif ($isEnd) {
                continue;
            } elseif ($isIframe) {
                $position = $this->iframe($html, $start, $tagEnd, $attributes, $activeKeys, $placeholder, $foreign['svg'] + $foreign['math'] > 0, $ends, $edits);
            } elseif ('plaintext' === $name) {
                break;
            } elseif (isset(self::RAW_TEXT[$name]) || 'noscript' === $name || 'template' === $name) {
                $position = $this->inertContent($html, $name, $tagEnd, $foreign['svg'] + $foreign['math'] > 0, $activeKeys, $placeholder, $ends, $edits);
            }
        }

        return $this->apply($html, $edits);
    }

    /**
     * @param array<string, string>                                                         $attributes
     * @param list<string>                                                                  $activeKeys
     * @param callable(string $key, string $src, array<string, string> $attributes): string $placeholder
     * @param array<string, int|false>                                                      $ends
     * @param list<array{int, int, string}>                                                 $edits
     *
     * @return int where the scan goes on
     */
    private function iframe(string $html, int $start, int $tagEnd, array $attributes, array $activeKeys, callable $placeholder, bool $inForeignContent, array &$ends, array &$edits): int
    {
        $src = $attributes['src'] ?? '';
        $key = '' === $src ? null : EmbedRules::serviceFor($src, $activeKeys);
        if (null === $key && $inForeignContent) {
            return $tagEnd;
        }

        // The content of an iframe is text to a browser, up to its end tag. An iframe left open must not hide the next one.
        $close = $this->endTag($html, 'iframe', $tagEnd, $ends);
        $nextIframe = stripos($html, '<iframe', $tagEnd);
        if (false !== $nextIframe && (false === $close || $nextIframe < $close)) {
            $end = $nextIframe;
        } elseif (false !== $close) {
            $closeEnd = strpos($html, '>', $close);
            $end = false === $closeEnd ? \strlen($html) : $closeEnd + 1;
        } else {
            $end = \strlen($html);
        }

        if (null !== $key) {
            unset($attributes['src']);
            $edits[] = [$start, $end, $placeholder($key, $src, $attributes)];
        }

        return $end;
    }

    /**
     * The content of a raw text element, a noscript or a template, that a browser may read as text or as markup.
     *
     * @param list<string>                                                                  $activeKeys
     * @param callable(string $key, string $src, array<string, string> $attributes): string $placeholder
     * @param array<string, int|false>                                                      $ends
     * @param list<array{int, int, string}>                                                 $edits
     *
     * @return int where the scan goes on
     */
    private function inertContent(string $html, string $name, int $contentStart, bool $inForeignContent, array $activeKeys, callable $placeholder, array &$ends, array &$edits): int
    {
        $close = $this->endTag($html, $name, $contentStart, $ends);

        if (false === $close) {
            // Text up to the end of the page for a browser; an unclosed noscript or template, or a foreign element, is read as markup.
            return isset(self::RAW_TEXT[$name]) && !$inForeignContent ? \strlen($html) : $contentStart;
        }

        // A noscript is text when scripts run and markup when they do not; in the content of an svg, a style or a script is markup, or text in an HTML integration point.
        if ('noscript' === $name || ($inForeignContent && isset(self::RAW_TEXT[$name]))) {
            $content = substr($html, $contentStart, $close - $contentStart);
            $rewritten = $this->scan($content, $activeKeys, $placeholder);
            if ($rewritten !== $content) {
                $edits[] = [$contentStart, $close, $rewritten];
            }
        }

        return $close;
    }

    /** Comments, doctypes, processing instructions and what a browser reads as a bogus comment. @return int the offset after it */
    private function skipNonTag(string $html, int $start): int
    {
        $length = \strlen($html);
        if (0 === substr_compare($html, '<!--', $start, 4)) {
            $from = $start + 4;
            if ('>' === ($html[$from] ?? '')) {
                return $from + 1;
            }
            if ('->' === substr($html, $from, 2)) {
                return $from + 2;
            }
            $plain = strpos($html, '-->', $from);
            $bang = strpos($html, '--!>', $from);
            if (false === $plain && false === $bang) {
                return $length;
            }

            return false === $bang || (false !== $plain && $plain < $bang) ? $plain + 3 : $bang + 4;
        }

        $end = strpos($html, '>', $start);

        return false === $end ? $length : $end + 1;
    }

    /**
     * Reads the attributes of a tag as the HTML tokenizer does: a quote opens a string only at the start of a value.
     *
     * @return array{int|null, array<string, string>, bool} the offset after the closing greater-than (null when the page ends inside the tag, which a browser drops), the attributes when asked, and whether the tag is self-closing
     */
    private function tag(string $html, int $from, bool $collect): array
    {
        $length = \strlen($html);
        $attributes = [];
        $i = $from;

        while (true) {
            $skipped = strspn($html, self::SPACE.'/', $i);
            $i += $skipped;
            if ($i >= $length) {
                return [null, [], false];
            }
            if ('>' === $html[$i]) {
                return [$i + 1, $attributes, $skipped > 0 && '/' === $html[$i - 1]];
            }

            $nameStart = $i;
            $i += 1 + strcspn($html, self::SPACE.'/>=', $i + 1);
            $name = strtolower(substr($html, $nameStart, $i - $nameStart));
            $value = '';

            $afterSpace = $i + strspn($html, self::SPACE, $i);
            if ('=' === ($html[$afterSpace] ?? '')) {
                $valueStart = $afterSpace + 1 + strspn($html, self::SPACE, $afterSpace + 1);
                if ($valueStart >= $length) {
                    return [null, [], false];
                }
                $first = $html[$valueStart];
                if ('"' === $first || "'" === $first) {
                    $close = strpos($html, $first, $valueStart + 1);
                    if (false === $close) {
                        return [null, [], false];
                    }
                    $value = substr($html, $valueStart + 1, $close - $valueStart - 1);
                    $i = $close + 1;
                } else {
                    $valueEnd = $valueStart + strcspn($html, self::SPACE.'>', $valueStart);
                    $value = substr($html, $valueStart, $valueEnd - $valueStart);
                    $i = $valueEnd;
                }
            }

            if ($collect && !isset($attributes[$name])) {
                $attributes[$name] = $this->decode($value);
            }
        }
    }

    /**
     * The offset of the next end tag of an element, or false. Searches are remembered: the offsets only grow, so a tag found
     * ahead of the scan stays the answer until the scan passes it, and the page is read once whatever the number of elements.
     *
     * @param array<string, int|false> $ends
     */
    private function endTag(string $html, string $name, int $from, array &$ends): int|false
    {
        if (\array_key_exists($name, $ends) && (false === $ends[$name] || $ends[$name] >= $from)) {
            return $ends[$name];
        }

        $needle = '</'.$name;
        $offset = $from;
        $found = false;
        while (false !== ($candidate = stripos($html, $needle, $offset))) {
            if (str_contains(self::SPACE.'/>', $html[$candidate + \strlen($needle)] ?? 'x')) {
                $found = $candidate;
                break;
            }
            $offset = $candidate + 1;
        }

        return $ends[$name] = $found;
    }

    /** A character reference is decoded once, as a browser does, with or without its semicolon for the numeric ones. */
    private function decode(string $value): string
    {
        if (!str_contains($value, '&')) {
            return $value;
        }

        $decoded = preg_replace_callback(
            '~&(?:#([0-9]+);?|#[xX]([0-9a-fA-F]+);?|[A-Za-z][A-Za-z0-9]*;)~',
            static function (array $found): string {
                if (isset($found[2])) {
                    $code = hexdec($found[2]);
                } elseif (isset($found[1])) {
                    $code = (int) $found[1];
                } else {
                    return html_entity_decode($found[0], \ENT_QUOTES | \ENT_HTML5);
                }
                $code = \is_float($code) || $code > 0x10FFFF || 0 === $code ? 0xFFFD : $code;

                return mb_chr($code, 'UTF-8') ?: "\u{FFFD}";
            },
            $value,
        );
        if (null === $decoded) {
            throw new \RuntimeException('The attributes of a tag could not be decoded ('.preg_last_error_msg().').');
        }

        return $decoded;
    }

    /** @param list<array{int, int, string}> $edits */
    private function apply(string $html, array $edits): string
    {
        if ([] === $edits) {
            return $html;
        }

        $out = '';
        $copied = 0;
        foreach ($edits as [$from, $to, $text]) {
            $out .= substr($html, $copied, $from - $copied).$text;
            $copied = $to;
        }

        return $out.substr($html, $copied);
    }
}
