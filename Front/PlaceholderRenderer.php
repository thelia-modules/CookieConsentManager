<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use Twig\Environment;

/** Renders the placeholder template of one service of a published configuration. */
final readonly class PlaceholderRenderer
{
    public function __construct(
        private TemplateContext $context,
        private Environment $twig,
    ) {
    }

    /** False on a route that is not a page of the theme: the Twig loader does not know the active theme yet. */
    public function canRender(): bool
    {
        return $this->twig->getLoader()->exists('CookieConsentManager/placeholder.html.twig');
    }

    /** @param array<string, string> $attributes iframe attributes without `src`, restored by the engine */
    public function render(FrontConfig $front, string $key, string $src, array $attributes): string
    {
        $attributes = array_filter($attributes, static fn (string $name): bool => 1 === preg_match('/^[a-z][a-z0-9_-]*$/D', $name), \ARRAY_FILTER_USE_KEY);
        $variables = $this->context->build($front);
        $service = null;
        foreach ($front->snapshot['services_resolved'] as $candidate) {
            if ($candidate['key'] === $key) {
                $service = $candidate;
            }
        }

        $purpose = array_values(array_filter($variables['purposes'], static fn (array $purpose): bool => $purpose['code'] === ($service['purpose'] ?? '')))[0] ?? null;

        return $this->twig->render('CookieConsentManager/placeholder.html.twig', $variables + [
            'service' => [
                'key' => $key,
                'name' => $service['name'] ?? $key,
                'purpose_label' => $purpose['label'] ?? '',
                'controller' => null === $service ? $key : TemplateContext::controller($service),
                'policy_url' => $service['policy_url'] ?? '',
            ],
            'attributes' => $attributes,
            'src' => $src,
        ]);
    }

    /** @return list<string> */
    public static function activeMediaKeys(FrontConfig $front): array
    {
        $keys = [];
        foreach ($front->config['services'] as $service) {
            if ('media' === $service['kind'] && isset(EmbedRules::rules()[$service['key']])) {
                $keys[] = $service['key'];
            }
        }

        return $keys;
    }
}
