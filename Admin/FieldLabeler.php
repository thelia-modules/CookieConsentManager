<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Catalogue\CatalogueReader;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Readable name of a configuration path (`services.crisp.parameters.crispID`), for diffs and error messages. */
final readonly class FieldLabeler
{
    private const DOMAIN = 'cookieconsentmanager.bo.default-twig';

    private const FIELDS = [
        'general.layout' => 'Layout',
        'general.renewal_months' => 'Renewal delay (months)',
        'general.enabled' => 'Banner enabled',
        'general.close_button' => 'Close button',
        'general.floating_button' => 'Floating "Manage my cookies" button',
        'general.reload_on_script_withdraw' => 'Reload the page when a script consent is withdrawn',
        'general.block_embedded_iframes' => 'Automatically block embedded iframes',
        'general.experimental_services' => 'Experimental services',
        'policy.url' => 'Cookie policy URL',
        'policy.page_title' => 'Cookie policy page title',
        'appearance.colors.primary' => 'Button color',
        'appearance.colors.surface' => 'Banner background',
        'appearance.colors.text' => 'Text color',
        'consent_mode.enabled' => 'Consent Mode enabled',
        'consent_mode.mode' => 'Consent Mode',
        'consent_mode.ads_data_redaction' => 'Ads data redaction',
        'consent_mode.url_passthrough' => 'URL passthrough',
        'consent_mode.wait_for_update_ms' => 'Wait for update (ms)',
        'consent_mode.regions' => 'Consent Mode regions',
        'consent_mode.uet.enabled' => 'Microsoft Advertising (UET) signal',
        'registry.retention_months' => 'Registry retention (months)',
        'registry.ip_mode' => 'IP option',
        'registry.link_customer' => 'Link to the customer account',
    ];

    private const TEXTS = [
        'title' => 'Title',
        'body' => 'Message',
        'accept' => 'Accept button',
        'reject' => 'Reject button',
        'customize' => 'Customize button',
        'close' => 'Close link',
        'allow' => 'Placeholder "Allow" button',
        'preferences' => 'Open preferences link',
        'free_text' => 'Free text (optional)',
    ];

    private const SERVICE_FIELDS = [
        'enabled' => 'Enabled',
        'purpose' => 'Purpose',
        'name' => 'Name',
        'controller' => 'Responsible (legal entity)',
        'policy_url' => 'Privacy policy URL',
        'kind' => 'Kind',
        'script_url' => 'Script URL (https)',
        'code' => 'Code run once the visitor consents (JavaScript)',
        'cookies' => 'Cookies',
        'justification' => 'Justification',
        'key' => 'Key',
    ];

    public function __construct(
        private CatalogueReader $catalogue,
        private TranslatorInterface $translator,
    ) {
    }

    /** @param array<string, mixed> $draft used for the names of custom services and purposes */
    public function label(string $path, string $locale, array $draft = []): string
    {
        $parts = explode('.', $path);

        if (isset(self::FIELDS[$path])) {
            return $this->t(self::FIELDS[$path]);
        }

        if ('presets' === $parts[0] && isset($parts[1])) {
            return $this->t('Preset attached').' ('.strtoupper($parts[1]).')';
        }

        if ('policy' === $parts[0] && 'urls' === ($parts[1] ?? null) && isset($parts[2])) {
            return $this->t('Cookie policy URL').' ('.strtoupper($parts[2]).')';
        }

        if ('services' === $parts[0] && isset($parts[1])) {
            return $this->service($parts, $locale, $draft);
        }

        if ('texts' === $parts[0] && isset($parts[1], $parts[2])) {
            return $this->t('Banner text').' ('.strtoupper($parts[1]).') › '.$this->t(self::TEXTS[$parts[2]] ?? $parts[2]);
        }

        if ('purposes' === $parts[0] && isset($parts[1])) {
            $code = ctype_digit($parts[1]) ? (string) ($draft['purposes'][(int) $parts[1]]['code'] ?? $parts[1]) : $parts[1];
            $name = $this->purposeName($code, $locale, $draft);
            $rest = \array_slice($parts, 2);

            return [] === $rest ? $name : $name.' › '.$this->t(ucfirst(str_replace('_', ' ', implode(' ', $rest))));
        }

        if ('exempt_measurement' === $parts[0]) {
            $last = end($parts);
            $detail = 'service' === $last ? $this->t('Service') : $this->t(CodeLabels::english('condition', (string) $last));

            return $this->t('Exempted measurement').(\count($parts) > 2 ? ' › '.$detail : '');
        }

        return $path;
    }

    /**
     * @param list<string>         $parts
     * @param array<string, mixed> $draft
     */
    private function service(array $parts, string $locale, array $draft): string
    {
        $key = $parts[1];
        $name = $this->catalogue->find($key)->name ?? null;
        foreach ((array) ($draft['services'] ?? []) as $service) {
            if (($service['key'] ?? null) === $key && isset($service['name'])) {
                $name ??= (string) $service['name'];
            }
        }
        if (null === $name || '' === trim($name)) {
            $name = 'custom_' === $key ? $this->t('New custom service') : ucfirst(str_replace(['_', '-'], ' ', $key));
        }

        if (!isset($parts[2])) {
            return $name;
        }
        if ('parameters' === $parts[2]) {
            if (!isset($parts[3])) {
                return $name.' › '.$this->t('Identifiers');
            }
            foreach ($this->catalogue->find($key)->params ?? [] as $param) {
                if ($param['name'] === $parts[3]) {
                    return $name.' › '.($param['label'][$locale] ?? $param['label']['en'] ?? $parts[3]);
                }
            }

            return $name.' › '.$parts[3];
        }

        return $name.' › '.$this->t(self::SERVICE_FIELDS[$parts[2]] ?? ucfirst(str_replace('_', ' ', $parts[2])));
    }

    /** @param array<string, mixed> $draft */
    private function purposeName(string $code, string $locale, array $draft): string
    {
        foreach ((array) ($draft['purposes'] ?? []) as $purpose) {
            if (($purpose['code'] ?? null) === $code) {
                return (string) ($purpose['label'][$locale] ?? $purpose['label']['en'] ?? $code);
            }
        }

        return $code;
    }

    private function t(string $message): string
    {
        return $this->translator->trans($message, [], self::DOMAIN);
    }
}
