<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Shows the Markdown guides of the module in the back-office: everything is escaped first, only a few constructs are rendered. */
final class GuideRenderer
{
    /** Guide => language => file. The key stays the French one: it is the `guide` parameter of the help screen. */
    public const GUIDES = [
        'administrateur' => ['fr' => 'fr/guide-administrateur.md', 'en' => 'administrator-guide.md'],
        'integrateur' => ['fr' => 'fr/guide-integrateur.md', 'en' => 'integrator-guide.md'],
    ];

    /** Guide files that the help screen serves, by file name: a link to one of them becomes a link to the screen. */
    private const GUIDE_FILES = [
        'administrator-guide.md' => 'administrateur',
        'guide-administrateur.md' => 'administrateur',
        'integrator-guide.md' => 'integrateur',
        'guide-integrateur.md' => 'integrateur',
    ];

    /** Anchors that the dashboard links to: topic => language => heading slug. */
    private const ANCHORS = [
        'template-override' => ['fr' => 'surcharger-un-gabarit-pas-a-pas', 'en' => 'overriding-a-template'],
        'theme-fonts' => ['fr' => 'polices-du-theme', 'en' => 'theme-fonts'],
    ];

    public function __construct(
        #[Autowire(value: __DIR__.'/../docs')]
        private readonly string $directory,
    ) {
    }

    /** French for a French back-office, English for every other language. */
    public static function language(string $locale): string
    {
        return 'fr' === strtolower(substr($locale, 0, 2)) ? 'fr' : 'en';
    }

    public static function anchor(string $topic, string $locale): string
    {
        return self::ANCHORS[$topic][self::language($locale)];
    }

    public function render(string $guide, string $locale = 'en'): ?string
    {
        $file = isset(self::GUIDES[$guide]) ? $this->directory.'/'.self::GUIDES[$guide][self::language($locale)] : null;
        if (null === $file || !is_file($file)) {
            return null;
        }

        return $this->convert((string) file_get_contents($file));
    }

    public function convert(string $markdown): string
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $html = [];
        /** @var string|null $open */
        $open = null;
        /** @var list<string>|null $code */
        $code = null;
        $paragraph = [];

        $count = \count($lines);
        for ($i = 0; $i < $count; ++$i) {
            $line = $lines[$i];

            if (null !== $code) {
                if (str_starts_with(trim($line), '```')) {
                    $html[] = '<pre class="border rounded p-2 bg-body-tertiary"><code>'.htmlspecialchars(implode("\n", $code)).'</code></pre>';
                    $code = null;
                } else {
                    $code[] = $line;
                }
                continue;
            }
            if (str_starts_with(trim($line), '```')) {
                $this->flush($paragraph, $html);
                $this->close($open, $html);
                $code = [];
                continue;
            }
            if ('' === trim($line)) {
                $this->flush($paragraph, $html);
                $this->close($open, $html);
                continue;
            }
            if (1 === preg_match('/^(#{1,4})\s+(.+)$/D', $line, $heading)) {
                $this->flush($paragraph, $html);
                $this->close($open, $html);
                $level = \strlen($heading[1]) + 1;
                $html[] = \sprintf('<h%d id="%s">%s</h%d>', $level, self::slug($heading[2]), $this->inline($heading[2]), $level);
                continue;
            }
            if (1 === preg_match('/^\s*[-*]\s+(.+)$/D', $line, $item) || 1 === preg_match('/^\s*\d+[.)]\s+(.+)$/D', $line, $item)) {
                $this->flush($paragraph, $html);
                $tag = 1 === preg_match('/^\s*\d/', $line) ? 'ol' : 'ul';
                if ($open !== $tag) {
                    $this->close($open, $html);
                    $html[] = '<'.$tag.'>';
                    $open = $tag;
                }
                $html[] = '<li>'.$this->inline($item[1]).'</li>';
                continue;
            }
            if (str_starts_with(ltrim($line), '|')) {
                $this->flush($paragraph, $html);
                $this->close($open, $html);
                $rows = [];
                while ($i < $count && str_starts_with(ltrim($lines[$i]), '|')) {
                    if (1 !== preg_match('/^\s*\|[\s:|-]+\|\s*$/D', $lines[$i])) {
                        $rows[] = array_map('trim', explode('|', trim(trim($lines[$i]), '|')));
                    }
                    ++$i;
                }
                --$i;
                $table = '<div class="table-responsive"><table class="table table-sm table-bordered">';
                foreach ($rows as $index => $cells) {
                    $cell = 0 === $index ? 'th' : 'td';
                    $table .= '<tr>'.implode('', array_map(fn (string $text): string => '<'.$cell.'>'.$this->inline($text).'</'.$cell.'>', $cells)).'</tr>';
                }
                $html[] = $table.'</table></div>';
                continue;
            }
            if (str_starts_with($line, '>')) {
                $this->flush($paragraph, $html);
                $this->close($open, $html);
                $html[] = '<blockquote class="border-start ps-3 text-secondary">'.$this->inline(ltrim(substr($line, 1))).'</blockquote>';
                continue;
            }
            $this->close($open, $html);
            $paragraph[] = $this->inline(trim($line));
        }
        $this->flush($paragraph, $html);
        $this->close($open, $html);

        return implode("\n", $html);
    }

    /**
     * @param list<string> $paragraph
     * @param list<string> $html
     */
    private function flush(array &$paragraph, array &$html): void
    {
        if ([] !== $paragraph) {
            $html[] = '<p>'.implode(' ', $paragraph).'</p>';
            $paragraph = [];
        }
    }

    /**
     * @param list<string> $html
     *
     * @param-out null $open
     */
    private function close(?string &$open, array &$html): void
    {
        if (null !== $open) {
            $html[] = '</'.$open.'>';
            $open = null;
        }
    }

    public static function slug(string $heading): string
    {
        $text = preg_replace('/^\d+[.)]\s*/', '', strip_tags($heading)) ?? $heading;
        $text = strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text));

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $text), '-');
    }

    private function inline(string $text): string
    {
        $text = htmlspecialchars($text, \ENT_QUOTES);
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text) ?? $text;
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', static function (array $match): string {
            $url = html_entity_decode($match[2], \ENT_QUOTES);
            if (1 === preg_match('~^(?:[\w./-]*/)?([\w.-]+\.md)(#[\w-]*)?$~iD', $url, $file)) {
                // The other Markdown files are not served by the back-office: only the guides have a page.
                $guide = self::GUIDE_FILES[$file[1]] ?? null;

                return null === $guide ? $match[1] : '<a href="?guide='.$guide.htmlspecialchars($file[2] ?? '', \ENT_QUOTES).'">'.$match[1].'</a>';
            }
            if (1 !== preg_match('~^(https?://|#)~i', $url)) {
                return $match[1];
            }

            return '<a href="'.htmlspecialchars($url, \ENT_QUOTES).'" rel="noopener noreferrer">'.$match[1].'</a>';
        }, $text) ?? $text;

        return $text;
    }
}
