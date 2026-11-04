<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use CookieConsentManager\Registry\RequestConsentReader;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * SPEC 10.2. These functions do not exist while the module is disabled: a theme must not call them
 * outside a template of the module (the Flexy theme of the module never does).
 */
final class ConsentTwigExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly RequestStack $requests,
        private readonly RequestConsentReader $reader,
        private readonly FrontConfigProvider $configs,
        private readonly PlaceholderRenderer $placeholders,
        private readonly TemplateContext $context,
        private readonly Environment $twig,
    ) {
    }

    /** @return array<string, string> available in every template, the ones of a theme included (annex E) */
    public function getGlobals(): array
    {
        return ['ccm_contract' => TemplateContext::CONTRACT];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('ccm_has_consent', $this->hasConsent(...)),
            new TwigFunction('ccm_has_purpose_consent', $this->hasPurposeConsent(...)),
            new TwigFunction('ccm_placeholder', $this->placeholder(...), ['is_safe' => ['html']]),
            new TwigFunction('ccm_open_link', $this->openLink(...), ['is_safe' => ['html']]),
        ];
    }

    public function hasConsent(string $service): bool
    {
        $request = $this->requests->getCurrentRequest();
        $front = null === $request ? null : $this->configs->forRequest($request);
        if (null === $request || null === $front || !$front->isLive()) {
            return false;
        }

        // A measurement exempt from consent runs until the visitor objects.
        if (\in_array($service, array_column($front->config['exempt'], 'key'), true)) {
            return 0 !== ($this->reader->state($request)['x'][$service] ?? 1);
        }

        return $this->reader->hasConsent($request, $service);
    }

    public function hasPurposeConsent(string $purpose): bool
    {
        $request = $this->requests->getCurrentRequest();
        $front = null === $request ? null : $this->configs->forRequest($request);

        return null !== $request && null !== $front && $front->isLive() && $this->reader->hasPurposeConsent($request, $purpose);
    }

    /** @param array<string, scalar> $attributes `src` becomes the source restored once the service is allowed */
    public function placeholder(string $service, array $attributes = []): string
    {
        $request = $this->requests->getCurrentRequest();
        $front = null === $request ? null : $this->configs->forRequest($request);
        if (null === $front || !\in_array($service, array_column($front->config['services'], 'key'), true)) {
            return '';
        }

        $attributes = array_map(static fn (mixed $value): string => (string) $value, array_change_key_case($attributes));
        $src = $attributes['src'] ?? '';
        unset($attributes['src']);

        return $this->placeholders->render($front, $service, $src, $attributes);
    }

    public function openLink(?string $label = null): string
    {
        $request = $this->requests->getCurrentRequest();
        $front = null === $request ? null : $this->configs->forRequest($request);
        if (null === $front) {
            return '';
        }

        $variables = $this->context->build($front);

        return $this->twig->render('CookieConsentManager/open-link.html.twig', $variables + ['label' => $label ?? $variables['texts']['preferences']]);
    }
}
