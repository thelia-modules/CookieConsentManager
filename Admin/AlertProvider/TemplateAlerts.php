<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin\AlertProvider;

use CookieConsentManager\Admin\Alert;
use CookieConsentManager\Admin\GuideRenderer;
use CookieConsentManager\Front\TemplateContext;
use CookieConsentManager\Front\TemplateContractChecker;
use CookieConsentManager\Publication\OverriddenTemplatesProvider;

/** Banner templates overridden by the theme, and those out of date with the template contract. */
final readonly class TemplateAlerts implements AlertProvider
{
    public function __construct(
        private OverriddenTemplatesProvider $templates,
        private TemplateContractChecker $contracts,
        private AlertText $text,
    ) {
    }

    public static function priority(): int
    {
        return 300;
    }

    public function alerts(array $draft): array
    {
        $alerts = [];

        if ([] !== $overridden = $this->templates->list()) {
            $alerts[] = new Alert(Alert::INFO, 'templates_overridden', $this->text->t('%count% banner template(s) overridden by the theme: they are not updated with the module.', ['%count%' => (string) \count($overridden)]), null, array_map(static fn (array $override): string => \sprintf('%s (%s)', $override['template'], $override['source']), $overridden));
        }

        if ([] !== $outdated = $this->contracts->outdated()) {
            $alerts[] = new Alert(
                Alert::DANGER,
                'templates_contract',
                $this->text->t('%count% overridden template(s) do not follow version %expected% of the template contract (%names%): update them from the module templates.', ['%count%' => (string) \count($outdated), '%expected%' => TemplateContext::CONTRACT_VERSION, '%names%' => implode(', ', array_map(static fn (array $override): string => $override['template'], $outdated))]),
                'ccm_admin_help',
                array_map(fn (array $override): string => $this->text->t('%template% (theme %theme%): declares version %declared%, expected %expected%', ['%template%' => $override['template'], '%theme%' => $override['source'], '%declared%' => $override['contract'] ?? $this->text->t('none'), '%expected%' => TemplateContext::CONTRACT_VERSION]), $outdated),
                ['guide' => 'integrateur'],
                GuideRenderer::anchor('template-override', $this->text->locale()),
            );
        }

        return $alerts;
    }
}
