<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use CookieConsentManager\Draft\DefaultSettings;
use Thelia\Model\ConfigQuery;

/** Variables of the front templates (listed in docs/integrator-guide.md, "Template variables"), built from a snapshot for one language. */
final readonly class TemplateContext
{
    public const CONTRACT = 'ccm-templates/1';
    public const CONTRACT_VERSION = '1';

    public function __construct(private FrontTexts $frontTexts)
    {
    }

    /** @return array<string, mixed> */
    public function build(FrontConfig $front): array
    {
        $config = $front->snapshot['config'];
        $locale = $front->config['locale'];
        $language = strtolower(substr($locale, 0, 2));

        $texts = $this->texts($config['texts'], $language);
        $labels = $this->frontTexts->forLanguage($language);
        $texts['remembered'] = str_replace('%duration%', CookieDuration::months((int) $config['general']['renewal_months'], $labels), $texts['remembered']);
        $services = [];
        $exemptKeys = array_column($front->config['exempt'], 'key');
        $exempt = [];
        foreach ($front->snapshot['services_resolved'] as $service) {
            $row = [
                'key' => $service['key'],
                'name' => $service['name'],
                'purpose' => $service['purpose'],
                'controller' => self::controller($service),
                'policy_url' => $service['policy_url'] ?? '',
                'cookies' => $service['cookies'],
                'cookie_details' => $this->cookieDetails($service, $labels),
            ];
            if (\in_array($service['key'], $exemptKeys, true)) {
                $exempt[] = $row;
            } else {
                $services[] = $row;
            }
        }

        $active = array_column($front->config['purposes'], 'code');
        $purposes = [];
        foreach ($config['purposes'] as $purpose) {
            if (!\in_array($purpose['code'], $active, true)) {
                continue;
            }
            $purposes[] = [
                'code' => $purpose['code'],
                'label' => $this->localized($purpose['label'], $language, $this->frontTexts->defaultPurpose($purpose['code'], $language)['label'] ?? null),
                'description' => $this->localized($purpose['description'], $language, $this->frontTexts->defaultPurpose($purpose['code'], $language)['description'] ?? null),
                'locked' => false,
                'services' => array_values(array_filter($services, static fn (array $service): bool => $service['purpose'] === $purpose['code'])),
            ];
        }

        return [
            'ccm_contract' => self::CONTRACT,
            'layout' => 'modal' === $config['general']['layout'] ? 'modal' : 'bar',
            'merchant_name' => (string) ConfigQuery::read('store_name', ''),
            'purposes' => $purposes,
            'texts' => $texts,
            'close_enabled' => (bool) $config['general']['close_button'],
            'floating_button' => (bool) $config['general']['floating_button'],
            'policy_url' => self::policyUrl($config['policy'] ?? [], $language),
            'preferences_url' => $front->config['preferencesUrl'] ?? '/ccm/preferences',
            'locale' => $locale,
            'language' => $language,
            'exempt_services' => $exempt,
            'colors' => $this->colors($config['appearance']['colors']),
            'mode' => $front->mode(),
        ];
    }

    /** @param array<string, mixed> $policy */
    public static function policyUrl(array $policy, string $language): string
    {
        $localized = $policy['urls'][$language] ?? null;
        if (\is_string($localized) && '' !== trim($localized)) {
            return $localized;
        }

        return (string) ($policy['url'] ?? '');
    }

    /**
     * The controller entered for the service, the name of the service while it is empty.
     *
     * @param array<string, mixed> $service
     */
    public static function controller(array $service): string
    {
        $controller = trim((string) ($service['controller'] ?? ''));

        return '' !== $controller ? $controller : (string) $service['name'];
    }

    /**
     * @param array<string, mixed>  $service
     * @param array<string, string> $labels
     *
     * @return list<array{name: string, duration: string}>
     */
    private function cookieDetails(array $service, array $labels): array
    {
        $details = [];
        foreach ($service['cookie_details'] ?? array_map(static fn (string $name): array => ['name' => $name], $service['cookies']) as $cookie) {
            $details[] = [
                'name' => (string) $cookie['name'],
                'duration' => CookieDuration::format(isset($cookie['duration_days']) ? (int) $cookie['duration_days'] : null, $labels, isset($cookie['duration_minutes']) ? (int) $cookie['duration_minutes'] : null),
            ];
        }

        return $details;
    }

    /**
     * Colors set by the administrator; empty while they are the defaults, so that the theme variables apply.
     *
     * @param array<string, string> $configured
     *
     * @return array<string, string>
     */
    private function colors(array $configured): array
    {
        $defaults = DefaultSettings::all()['appearance']['colors'];
        $colors = [];
        foreach (['primary', 'surface', 'text'] as $name) {
            $value = strtolower((string) ($configured[$name] ?? ''));
            $colors[$name] = 1 === preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : $defaults[$name];
        }

        if ($colors === array_map('strtolower', $defaults)) {
            return [];
        }

        $colors['on_primary'] = $this->luminance($colors['primary']) > 0.4 ? '#111111' : '#ffffff';

        return $colors;
    }

    private function luminance(string $hex): float
    {
        $linear = static fn (int $channel): float => ($channel /= 255) <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $linear((int) hexdec(substr($hex, 1, 2))) + 0.7152 * $linear((int) hexdec(substr($hex, 3, 2))) + 0.0722 * $linear((int) hexdec(substr($hex, 5, 2)));
    }

    /**
     * What the administrator typed for the language, else the default text of that language when the
     * module ships one, else what was typed or proposed in English: a language never mixes two others.
     *
     * @param array<string, array<string, string>> $configured
     *
     * @return array<string, string>
     */
    public function texts(array $configured, string $language): array
    {
        $texts = $this->frontTexts->forLanguage($language);
        $own = FrontTexts::supports($language) ? $this->frontTexts->defaultTexts($language) : [];
        $english = $this->frontTexts->defaultTexts('en');

        foreach (array_keys($english) as $key) {
            foreach ([$configured[$language][$key] ?? null, $own[$key] ?? null, $configured['en'][$key] ?? null, $english[$key]] as $candidate) {
                if (\is_string($candidate) && '' !== trim($candidate)) {
                    $texts[$key] = $candidate;
                    break;
                }
            }
            $texts[$key] ??= '';
        }

        return $texts;
    }

    /** @param array<string, string> $labels */
    public function localized(array $labels, string $language, ?string $default): string
    {
        $own = FrontTexts::supports($language) ? $default : null;
        foreach ([$labels[$language] ?? null, $own, $labels['en'] ?? null, ...array_values($labels)] as $candidate) {
            if (\is_string($candidate) && '' !== trim($candidate)) {
                return $candidate;
            }
        }

        return '';
    }
}
