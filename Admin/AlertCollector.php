<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Catalogue\CookieDurations;
use CookieConsentManager\Draft\ServiceControllers;
use CookieConsentManager\Front\FrontTexts;
use CookieConsentManager\Front\ServedHashResolver;
use CookieConsentManager\Front\TemplateContext;
use CookieConsentManager\Front\TemplateContractChecker;
use CookieConsentManager\Model\Version;
use CookieConsentManager\Preset\PresetApplier;
use CookieConsentManager\Preset\PresetCatalogue;
use CookieConsentManager\Preset\PresetDeviation;
use CookieConsentManager\Publication\ModuleVersion;
use CookieConsentManager\Publication\OverriddenTemplatesProvider;
use CookieConsentManager\Publication\ServedScriptHasher;
use CookieConsentManager\Publication\VersionRepository;
use CookieConsentManager\Registry\LastPurgeStore;
use CookieConsentManager\Registry\RegistryPurger;
use CookieConsentManager\Validation\ConflictingModuleDetector;
use CookieConsentManager\Validation\DraftValidator;
use CookieConsentManager\Validation\ValidationIssue;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** The alerts of the dashboard (docs/administrator-guide.md, "Dashboard"): each one is a measured fact, with where to act. */
final readonly class AlertCollector
{
    public const DOMAIN = 'cookieconsentmanager.bo.default-twig';

    public function __construct(
        private DraftValidator $validator,
        private CatalogueReader $catalogue,
        private ConflictingModuleDetector $modules,
        private OverriddenTemplatesProvider $templates,
        private VersionRepository $versions,
        private ServedHashResolver $served,
        private RegistryPurger $purger,
        private LastPurgeStore $lastPurge,
        private ActiveLanguages $languages,
        private ThemeFontScanner $fonts,
        private TranslatorInterface $translator,
        private TemplateContractChecker $contracts,
        private PolicyUrlAlert $policy,
        private FieldLabeler $labels,
        private ModuleVersion $moduleVersion,
        private ServedScriptHasher $scripts,
        private ServiceControllers $controllers,
        private PresetCatalogue $presets,
        private PresetApplier $applier,
        private PresetDeviation $deviation,
        private CookieDurations $durations,
        private TermsPolicyAlert $termsPolicy,
        private EmbeddedMediaAlert $embeddedMedia,
        private ThemeHookScanner $themeHooks,
    ) {
    }

    /**
     * @param array<string, mixed> $draft
     *
     * @return list<Alert>
     */
    public function collect(array $draft): array
    {
        $alerts = [];

        foreach ($this->validator->validate($draft) as $issue) {
            if ('environment_unconfirmed' === $issue->code) {
                $alerts[] = new Alert(Alert::DANGER, 'environment_unconfirmed', $this->t('"%field%" was imported from another site and is not confirmed yet: check it for this shop.', ['%field%' => $this->labels->label($issue->field, $this->locale(), $draft)]), 'ccm_admin_versions');
            } elseif ('policy_missing' === $issue->code) {
                $alerts[] = new Alert(Alert::DANGER, 'policy_missing', $this->t('No privacy policy page is set: create it (Folders and Pages menu of the back-office), then enter its address in the "Privacy policy URL" field.'), 'ccm_admin_dashboard');
            } elseif (\in_array($issue->code, ['implicit_consent', 'label_gap'], true)) {
                $alerts[] = new Alert(Alert::DANGER, $issue->code.'_'.str_replace('.', '_', $issue->field), $this->t('Draft, %field%: %message%', ['%field%' => $this->labels->label($issue->field, $this->locale(), $draft), '%message%' => $issue->message]), 'ccm_admin_appearance');
            } elseif ($issue->isError()) {
                $alerts[] = new Alert(Alert::DANGER, 'draft_'.$issue->code, $this->t('Draft, %field%: %message%', ['%field%' => $this->labels->label($issue->field, $this->locale(), $draft), '%message%' => $issue->message]), $this->routeFor($issue));
            }
        }

        $published = null === ($latest = $this->versions->latest()) ? $draft['policy'] : ($this->versions->snapshot($latest)['config']['policy'] ?? []);
        foreach ($this->policyUrls($published, array_keys($draft['presets'])) as $suffix => $policyUrl) {
            if (null !== $policyAlert = $this->policy->check($policyUrl, $suffix)) {
                $alerts[] = $policyAlert;
            }
        }

        array_push($alerts, ...$this->termsPolicy->collect($draft));
        if (null !== $mediaAlert = $this->embeddedMedia->check($draft, $this->locale())) {
            $alerts[] = $mediaAlert;
        }

        $this->languages($draft, $alerts);
        $this->presets($draft, $alerts);
        $this->presetDeviations($draft, $alerts);
        $this->durations($draft, $alerts);
        $this->emitters($alerts, $draft);
        $this->services($draft, $alerts);

        if ('advanced' === $draft['consent_mode']['mode']) {
            $alerts[] = new Alert(Alert::DANGER, 'advanced_mode', $this->t('Consent Mode is advanced: Google tags load before the visitor chooses and send pings (screen size, browser data) even after a refusal. No authority has validated this mode for visitors in France: get your legal contact\'s written agreement, or go back to the basic mode.'), 'ccm_admin_consent_mode');
        }

        if ($draft['general']['renewal_months'] > 6) {
            $alerts[] = new Alert(Alert::WARNING, 'renewal_long', $this->t('The choice is asked again after %months% months: the CNIL recommends 6 months at most.', ['%months%' => (string) $draft['general']['renewal_months']]), 'ccm_admin_dashboard', [], [], 'general_renewal');
        }

        if ([] !== $overridden = $this->templates->list()) {
            $alerts[] = new Alert(Alert::INFO, 'templates_overridden', $this->t('%count% banner template(s) overridden by the theme: they are not updated with the module.', ['%count%' => (string) \count($overridden)]), null, array_map(static fn (array $override): string => \sprintf('%s (%s)', $override['template'], $override['source']), $overridden));
        }

        if ([] !== $outdated = $this->contracts->outdated()) {
            $alerts[] = new Alert(
                Alert::DANGER,
                'templates_contract',
                $this->t('%count% overridden template(s) do not follow version %expected% of the template contract (%names%): update them from the module templates.', ['%count%' => (string) \count($outdated), '%expected%' => TemplateContext::CONTRACT_VERSION, '%names%' => implode(', ', array_map(static fn (array $override): string => $override['template'], $outdated))]),
                'ccm_admin_help',
                array_map(fn (array $override): string => $this->t('%template% (theme %theme%): declares version %declared%, expected %expected%', ['%template%' => $override['template'], '%theme%' => $override['source'], '%declared%' => $override['contract'] ?? $this->t('none'), '%expected%' => TemplateContext::CONTRACT_VERSION]), $outdated),
                ['guide' => 'integrateur'],
                'surcharger-un-gabarit-pas-a-pas',
            );
        }

        $this->served($alerts);

        if ($this->purger->isLate()) {
            $last = $this->lastPurge->read();
            $alerts[] = new Alert(Alert::DANGER, 'purge_late', null === $last
                ? $this->t('The registry was never purged: purge it from the registry screen, or have a scheduled purge set up.')
                : $this->t('The registry was last purged on %date% (more than 7 days ago): purge it from the registry screen, or have a scheduled purge set up.', ['%date%' => $last->format('Y-m-d')]), 'ccm_admin_registry', ['ccm:registry:purge']);
        }

        if ([] !== $missingHooks = $this->themeHooks->missing()) {
            $alerts[] = new Alert(Alert::DANGER, 'theme_hooks_missing', $this->t('The active front theme does not call the theme hook(s) %hooks%: the banner and the consent script are not injected, so visitors are never asked. Use a Twig theme such as Flexy, or add the hook call to the theme layout.', ['%hooks%' => implode(', ', $missingHooks)]), 'ccm_admin_help', $missingHooks);
        }

        if ([] !== $files = $this->fonts->findings()) {
            $alerts[] = new Alert(Alert::WARNING, 'google_fonts', $this->t('The theme loads its fonts from Google before any choice (%count% file(s)): visitors\' IP addresses reach Google without consent. This comes from the theme, not from this module: host the fonts on your own server.', ['%count%' => (string) \count($files)]), 'ccm_admin_help', $files, [], 'polices-du-theme');
        }

        usort($alerts, static fn (Alert $a, Alert $b): int => [self::rank($a->severity)] <=> [self::rank($b->severity)]);

        return $alerts;
    }

    private static function rank(string $severity): int
    {
        return match ($severity) {
            Alert::DANGER => 0,
            Alert::WARNING => 1,
            default => 2,
        };
    }

    /**
     * @param array<string, mixed> $draft
     * @param list<Alert>          $alerts
     */
    private function languages(array $draft, array &$alerts): void
    {
        foreach ($this->languages->codes() as $code) {
            $provided = FrontTexts::supports($code);
            if (!isset($draft['texts'][$code]) && !$provided) {
                $alerts[] = new Alert(Alert::DANGER, 'language_without_text', $this->t('The language "%code%" is active but has no banner text (English is shown).', ['%code%' => $code]), 'ccm_admin_appearance');
                continue;
            }

            $missing = [];
            foreach ($provided ? [] : ['title', 'body', 'accept', 'reject', 'customize', 'close', 'allow', 'preferences'] as $field) {
                if ('' === trim((string) ($draft['texts'][$code][$field] ?? ''))) {
                    $missing[] = $field;
                }
            }
            foreach ($draft['purposes'] as $purpose) {
                if ($provided && isset(FrontTexts::DEFAULT_PURPOSES[$purpose['code']])) {
                    continue;
                }
                if ('' === trim((string) ($purpose['label'][$code] ?? '')) || '' === trim((string) ($purpose['description'][$code] ?? ''))) {
                    $missing[] = 'purposes.'.$purpose['code'];
                }
            }
            if ([] !== $missing) {
                $alerts[] = new Alert(Alert::DANGER, 'language_incomplete_'.$code, $this->t('The translation "%code%" is incomplete: the missing texts are shown in English.', ['%code%' => $code]), 'ccm_admin_appearance', array_map(fn (string $path): string => $this->labels->label(str_starts_with($path, 'purposes.') ? $path : 'texts.'.$code.'.'.$path, $this->locale(), $draft), $missing));
            }
        }
    }

    /**
     * @param array<string, mixed> $draft
     * @param list<Alert>          $alerts
     */
    private function presets(array $draft, array &$alerts): void
    {
        $attached = [];
        foreach ($draft['presets'] as $code) {
            if (null !== $preset = $this->presets->find($code)) {
                $attached[$code] = $preset;
            }
        }

        foreach ($this->applier->contradictions(array_values($attached)) as $contradiction) {
            $alerts[] = new Alert(
                Alert::WARNING,
                'presets_contradict_'.$contradiction['first'].'_'.$contradiction['second'],
                $this->t('The presets "%first%" and "%second%" attached to different languages disagree on settings shared by every language: only the preset of the main language is applied to them. Check these settings.', ['%first%' => $contradiction['first'], '%second%' => $contradiction['second']]),
                'ccm_admin_presets',
                array_map(fn (string $path): string => $this->labels->label($path, $this->locale(), $draft), $contradiction['paths']),
            );
        }
    }

    /**
     * The preset of the main language is the reference: a setting shared by every language that protects visitors
     * less than it is a red alert naming the setting and the value of the preset. Nothing is blocked.
     *
     * @param array<string, mixed> $draft
     * @param list<Alert>          $alerts
     */
    private function presetDeviations(array $draft, array &$alerts): void
    {
        $code = $draft['presets'][$this->languages->mainCode()] ?? null;
        if (null === $code || null === $preset = $this->presets->find($code)) {
            return;
        }

        foreach ($this->deviation->between($draft, $preset) as $deviation) {
            $alerts[] = new Alert(
                Alert::DANGER,
                'preset_deviation_'.str_replace('.', '_', $deviation['path']),
                $this->t('The setting "%field%" is %value% while the preset "%preset%" sets %reference%: visitors are protected less than the preset intends.', [
                    '%field%' => $this->labels->label($deviation['path'], $this->locale(), $draft),
                    '%value%' => $this->shown($deviation['current']),
                    '%preset%' => $preset->label,
                    '%reference%' => $this->shown($deviation['reference']),
                ]),
                str_starts_with($deviation['path'], 'consent_mode') ? 'ccm_admin_consent_mode' : ('registry.' === substr($deviation['path'], 0, 9) ? 'ccm_admin_registry' : 'ccm_admin_dashboard'),
            );
        }
    }

    private function shown(mixed $value): string
    {
        return \is_bool($value) ? $this->t($value ? 'Yes' : 'No') : (string) $value;
    }

    /**
     * Under a preset attached to another language than the main one, an active service whose cookies have no known
     * lifetime leaves the visitor without the duration (RGPD art. 13). On the main language the validator refuses it.
     *
     * @param array<string, mixed> $draft
     * @param list<Alert>          $alerts
     */
    private function durations(array $draft, array &$alerts): void
    {
        if ([] === $draft['presets'] || isset($draft['presets'][$this->languages->mainCode()])) {
            return;
        }

        $without = array_map(fn (string $key): string => $this->labels->label('services.'.$key, $this->locale(), $draft), array_keys($this->durations->withoutLifetime($draft['services'], $this->catalogue)));

        if ([] !== $without) {
            $alerts[] = new Alert(Alert::DANGER, 'cookie_duration_missing', $this->t('No cookie lifetime is known for: %services%. Visitors are not told how long these cookies last: enter the durations of the publisher on the service.', ['%services%' => implode(', ', $without)]), 'ccm_admin_services');
        }
    }

    /**
     * Addresses to check: the general one, then each language that has a preset and its own address.
     *
     * @param array<string, mixed> $policy          published policy section
     * @param list<string|int>     $presetLanguages
     *
     * @return array<string, string> alert code suffix => address
     */
    private function policyUrls(array $policy, array $presetLanguages): array
    {
        $urls = [];
        if (\is_string($policy['url'] ?? null) && '' !== $policy['url']) {
            $urls[''] = $policy['url'];
        }
        foreach ($presetLanguages as $language) {
            $url = TemplateContext::policyUrl($policy, (string) $language);
            if ('' !== $url && !\in_array($url, $urls, true)) {
                $urls['_'.$language] = $url;
            }
        }

        return $urls;
    }

    /**
     * @param array<string, mixed> $draft
     * @param list<Alert>          $alerts
     */
    private function emitters(array &$alerts, array $draft): void
    {
        $videos = ['youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'dailymotion' => 'Dailymotion'];
        $activeVideos = array_filter($draft['services'], static fn (array $service): bool => $service['enabled'] && isset($videos[$service['key']]));
        $emitters = [
            'GoogleTagManager' => [Alert::DANGER, 'The GoogleTagManager module is active: it loads Google Tag Manager in the head with no consent. Disable it and enable the Tag Manager service of this module.'],
            'ReCaptcha' => [Alert::WARNING, 'The ReCaptcha module is active: it contacts Google on the pages with a form. Enable the reCAPTCHA service (placeholder on the form).'],
        ];
        foreach ($emitters as $code => [$severity, $message]) {
            if ($this->modules->isActive($code)) {
                $alerts[] = new Alert($severity, 'emitter_'.strtolower($code), $this->t($message), 'ccm_admin_services');
            }
        }
        if ([] === $activeVideos && $this->modules->isActive('TheliaBlocks')) {
            $alerts[] = new Alert(Alert::WARNING, 'emitter_theliablocks', $this->t('TheliaBlocks is active: its video blocks embed third-party players. Enable the video services you use (%services%): the iframe is then replaced by a placeholder until the visitor consents.', ['%services%' => implode(', ', $videos)]), 'ccm_admin_services');
        }
    }

    /**
     * @param array<string, mixed> $draft
     * @param list<Alert>          $alerts
     */
    private function services(array $draft, array &$alerts): void
    {
        $enabled = array_column(array_filter($draft['services'], static fn (array $service): bool => $service['enabled']), null, 'key');

        $google = array_filter(array_keys($enabled), fn (string $key): bool => 'googletagmanager' !== $key && ($this->catalogue->find($key)->googleTag ?? false));
        if (isset($enabled['googletagmanager']) && [] !== $google) {
            $alerts[] = new Alert(Alert::DANGER, 'gtag_twice', $this->t('gtag is loaded twice: Tag Manager and %services% are both active. Keep one.', ['%services%' => implode(', ', $google)]), 'ccm_admin_services');
        }

        $without = array_values(array_map(fn (array $service): string => $this->labels->label('services.'.$service['key'], $this->locale(), $draft), array_filter($enabled, fn (array $service): bool => '' === $this->controllers->effective($service))));
        if ([] !== $without && [] === $draft['presets']) {
            $alerts[] = new Alert(Alert::WARNING, 'controller_missing', $this->t('No responsible (legal entity) is set for: %services%. Visitors see the product name instead.', ['%services%' => implode(', ', $without)]), 'ccm_admin_services');
        }

        foreach ($enabled as $service) {
            if ('essential' === $service['purpose'] && isset($service['module'])) {
                $alerts[] = new Alert(Alert::WARNING, 'strictly_necessary_'.$service['key'], $this->t('The service "%name%" is declared strictly necessary and runs without consent. Justification: %justification%', ['%name%' => $service['name'], '%justification%' => $service['justification'] ?? '']), 'ccm_admin_services');
            }
        }
    }

    /** @param list<Alert> $alerts */
    private function served(array &$alerts): void
    {
        $latest = $this->versions->latest();
        if (null === $latest) {
            return;
        }

        $served = $this->served->forVersion($latest);
        if ($served !== $latest->getServedHash()) {
            $alerts[] = new Alert(Alert::DANGER, 'served_hash', $this->t('Version %version% no longer matches what visitors are served. %cause% Publish again to align them.', ['%version%' => (string) $latest->getVersion(), '%cause%' => $this->servedCause($latest)]), 'ccm_admin_versions', [\sprintf('served %s / published %s', $served, $latest->getServedHash())]);
        }
    }

    private function servedCause(Version $version): string
    {
        $snapshot = $this->versions->snapshot($version);
        $current = $this->moduleVersion->current();
        if ($version->getModuleVersion() !== $current) {
            return $this->t('The version was published with module %published%, the module is now %current%.', ['%published%' => $version->getModuleVersion(), '%current%' => $current]);
        }

        if ($this->scripts->hash($snapshot) !== $version->getJsHash()) {
            return $this->t('The script of the module changed since the publication (module update).');
        }

        $before = array_column((array) ($snapshot['templates'] ?? []), 'hash', 'template');
        $now = array_column($this->templates->list(), 'hash', 'template');
        $changed = array_keys(array_diff_assoc($now, $before) + array_diff_assoc($before, $now));
        if ([] !== $changed) {
            return $this->t('The overridden template(s) %names% changed since the publication.', ['%names%' => implode(', ', $changed)]);
        }

        return $this->t('Nothing changed in the module or in the theme since the publication: the version was probably published before the script fingerprint was corrected.');
    }

    private function locale(): string
    {
        return $this->translator instanceof LocaleAwareInterface ? substr($this->translator->getLocale(), 0, 2) : 'en';
    }

    private function routeFor(ValidationIssue $issue): string
    {
        return match (true) {
            \in_array($issue->code, ['policy_url_terms', 'policy_url_preset_missing'], true) => 'ccm_admin_policy_template',
            str_starts_with($issue->field, 'services.'), str_starts_with($issue->field, 'exempt_measurement') => 'ccm_admin_services',
            str_starts_with($issue->field, 'consent_mode') => 'ccm_admin_consent_mode',
            default => 'ccm_admin_dashboard',
        };
    }

    /** @param array<string, string> $parameters */
    private function t(string $message, array $parameters = []): string
    {
        return $this->translator->trans($message, $parameters, self::DOMAIN);
    }
}
