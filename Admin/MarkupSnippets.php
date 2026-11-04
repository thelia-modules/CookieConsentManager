<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** The HTML an editor pastes in a page for the services that work on markup (SPEC 5.2). */
final class MarkupSnippets
{
    /** @var array<string, string>|null */
    private ?array $snippets = null;

    public function __construct(
        #[Autowire(value: __DIR__.'/../Resources/catalogue/catalogue.json')]
        private readonly string $cataloguePath,
    ) {
    }

    /** The snippet as pasted: every "name (a | b)" placeholder of the upstream catalogue becomes its first value. */
    public function for(string $key): ?string
    {
        $snippet = $this->all()[$key] ?? null;

        return null === $snippet ? null : $this->firstChoices($snippet);
    }

    /** The same snippet in a Twig template, the placeholder values becoming variables of the template. */
    public function twigExample(string $key, string $comment = 'Template of your theme: the block stays inert until the visitor consents.'): ?string
    {
        $snippet = $this->all()[$key] ?? null;
        if (null === $snippet) {
            return null;
        }

        $values = ['width' => '560', 'height' => '315'];
        $variables = [];
        $body = preg_replace_callback('/(data-[\w-]+)="([a-z][a-z_]*\d?)"/', static function (array $match) use (&$variables, $values): string {
            $variables[$match[2]] = $values[$match[2]] ?? strtoupper($match[2]);

            return $match[1].'="{{ '.$match[2].' }}"';
        }, $snippet) ?? $snippet;

        $lines = [\sprintf('{# %s #}', $comment)];
        foreach ($variables as $name => $value) {
            $lines[] = \sprintf("{%% set %s = '%s' %%}", $name, $value);
        }

        return implode("\n", [...$lines, $this->firstChoices($body)]);
    }

    private function firstChoices(string $snippet): string
    {
        return preg_replace('/="[\w-]+\s\(([^()|"]+?)\s*\|[^()"]*\)"/', '="$1"', $snippet) ?? $snippet;
    }

    /** @return array<string, string> */
    private function all(): array
    {
        if (null !== $this->snippets) {
            return $this->snippets;
        }

        $this->snippets = [];
        $content = is_file($this->cataloguePath) ? file_get_contents($this->cataloguePath) : false;
        foreach (false === $content ? [] : (array) json_decode($content, true, 512, \JSON_THROW_ON_ERROR) as $row) {
            if (!empty($row['needsMarkup']) && \is_string($row['markup'] ?? null) && '' !== $row['markup'] && null === ($row['excluded'] ?? null)) {
                $this->snippets[(string) $row['key']] = $row['markup'];
            }
        }

        return $this->snippets;
    }
}
