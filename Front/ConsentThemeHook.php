<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Content\Slot\ContentSlotResolverInterface;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Twig\Environment;

/**
 * Nothing is rendered while no version is published, except for the preview of the draft. The
 * head part comes first of every hook answering `layout.head.top`: the Consent Mode defaults must
 * be pushed before any tag of the page.
 */
#[AsTaggedItem(priority: \PHP_INT_MAX)]
final readonly class ConsentThemeHook implements ThemeHookInterface
{
    private const HEAD = 'layout.head.top';
    private const BODY = 'layout.body.top';
    private const FOOTER = 'layout.footer.top';

    public function __construct(
        private RequestStack $requests,
        private FrontConfigProvider $configs,
        private TemplateContext $context,
        private FrontTexts $texts,
        private StyleSheet $style,
        private Environment $twig,
        #[Autowire(value: __DIR__.'/../assets/js/head.js')]
        private string $headScript,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return \in_array($hookName, [self::HEAD, self::BODY, self::FOOTER], true);
    }

    public function render(string $hookName, array $parameters): string
    {
        $request = $this->requests->getMainRequest();
        if (null === $request) {
            return '';
        }

        // The slot `footer_links` carries the link from Thelia 3.2: the fallback is for the older cores.
        if (self::FOOTER === $hookName && interface_exists(ContentSlotResolverInterface::class)) {
            return '';
        }

        $front = $this->configs->forRequest($request);
        if (null === $front) {
            return '';
        }

        return match ($hookName) {
            self::HEAD => $this->head($front),
            self::BODY => $this->body($front),
            default => $this->footer($front),
        };
    }

    private function head(FrontConfig $front): string
    {
        $json = json_encode($front->config, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG | \JSON_HEX_AMP);

        return \sprintf(
            '<script type="application/json" id="ccm-config">%s</script>'."\n".'<script>%s</script>'."\n".'<style id="ccm-style">%s</style>'."\n".'<script defer src="%s"></script>'."\n",
            $json,
            file_get_contents($this->headScript),
            $this->style->minified(),
            htmlspecialchars($front->config['script'], \ENT_QUOTES),
        );
    }

    private function body(FrontConfig $front): string
    {
        $variables = $this->context->build($front);
        $html = $this->twig->render('CookieConsentManager/banner.html.twig', $variables)
            .$this->twig->render('CookieConsentManager/preferences-panel.html.twig', $variables)
            .'<template data-ccm-placeholder-template>'
            .$this->twig->render('CookieConsentManager/placeholder.html.twig', $variables + ['template_mode' => true, 'service' => ['key' => '{{key}}', 'name' => '{{name}}', 'purpose_label' => '', 'controller' => '{{controller}}', 'policy_url' => '{{policyUrl}}'], 'attributes' => []])
            .'</template>'
            .'<script type="application/json" id="ccm-services-meta">'.$this->servicesMeta($variables).'</script>'
            .'<script type="application/json" id="ccm-messages">'.$this->encode($this->texts->messages($variables['language'])).'</script>';

        if ($variables['floating_button']) {
            $html .= $this->twig->render('CookieConsentManager/floating-button.html.twig', $variables + ['label' => $variables['texts']['preferences'], 'position' => 'bottom-left']);
        }

        return $html;
    }

    /** @param array<string, mixed> $variables */
    private function servicesMeta(array $variables): string
    {
        $meta = [];
        foreach ($variables['purposes'] as $purpose) {
            foreach ($purpose['services'] as $service) {
                $meta[$service['key']] = ['controller' => $service['controller'], 'policy_url' => $service['policy_url']];
            }
        }

        return $this->encode((object) $meta);
    }

    private function encode(object|array $data): string
    {
        return json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_HEX_AMP);
    }

    private function footer(FrontConfig $front): string
    {
        $variables = $this->context->build($front);

        return $this->twig->render('CookieConsentManager/open-link.html.twig', $variables + ['label' => $variables['texts']['preferences'], 'wrapper' => true]);
    }
}
