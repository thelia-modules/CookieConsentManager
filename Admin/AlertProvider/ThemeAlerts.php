<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin\AlertProvider;

use CookieConsentManager\Admin\Alert;
use CookieConsentManager\Admin\GuideRenderer;
use CookieConsentManager\Admin\ThemeFontScanner;
use CookieConsentManager\Admin\ThemeHookScanner;

/** What the active front theme does that the consent manager cannot control. */
final readonly class ThemeAlerts implements AlertProvider
{
    public function __construct(
        private ThemeHookScanner $themeHooks,
        private ThemeFontScanner $fonts,
        private AlertText $text,
    ) {
    }

    public static function priority(): int
    {
        return 100;
    }

    public function alerts(array $draft): array
    {
        $alerts = [];

        if ([] !== $missingHooks = $this->themeHooks->missing()) {
            $alerts[] = new Alert(Alert::DANGER, 'theme_hooks_missing', $this->text->t('The active front theme does not call the theme hook(s) %hooks%: the banner and the consent script are not injected, so visitors are never asked. Use a Twig theme such as Flexy, or add the hook call to the theme layout.', ['%hooks%' => implode(', ', $missingHooks)]), 'ccm_admin_help', $missingHooks);
        }

        if ([] !== $files = $this->fonts->findings()) {
            $alerts[] = new Alert(Alert::WARNING, 'google_fonts', $this->text->t('The theme loads its fonts from Google before any choice (%count% file(s)): visitors\' IP addresses reach Google without consent. This comes from the theme, not from this module: host the fonts on your own server.', ['%count%' => (string) \count($files)]), 'ccm_admin_help', $files, [], GuideRenderer::anchor('theme-fonts', $this->text->locale()));
        }

        return $alerts;
    }
}
