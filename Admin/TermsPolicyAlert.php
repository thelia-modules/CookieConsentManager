<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Front\TemplateContext;
use CookieConsentManager\Validation\FrenchPresetLanguages;
use CookieConsentManager\Validation\TermsPageUrl;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * An active language whose cookie policy address leads to the terms of sale tells the visitor nothing about cookies (RGPD art. 13).
 * Where the French preset applies the validator already refuses it: this alert covers the other languages, with or without a preset.
 * A fallback that no active language uses yet still reaches the visitors of a language activated later, before anyone reads the alerts.
 */
final readonly class TermsPolicyAlert
{
    private const DOMAIN = 'cookieconsentmanager.bo.default-twig';

    public function __construct(
        private FrenchPresetLanguages $frenchLanguages,
        private ActiveLanguages $languages,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, mixed> $draft normalized
     *
     * @return list<Alert>
     */
    public function collect(array $draft): array
    {
        $refused = $this->frenchLanguages->of($draft);
        $alerts = [];
        $fallbackUsed = false;
        foreach ($this->languages->codes() as $language) {
            $own = \is_string($draft['policy']['urls'][$language] ?? null) && '' !== trim($draft['policy']['urls'][$language]);
            $fallbackUsed = $fallbackUsed || !$own;
            $url = trim(TemplateContext::policyUrl($draft['policy'], $language));
            if (\in_array($language, $refused, true) || '' === $url || !TermsPageUrl::matches($url)) {
                continue;
            }

            $alerts[] = new Alert(
                Alert::DANGER,
                'policy_url_terms_'.$language,
                $this->translator->trans($own
                    ? 'The cookie policy address of the language "%language%" (%url%) is a page of terms and conditions, not a cookie policy: publish a page dedicated to cookies.'
                    : 'The fallback address of the cookie policy (%url%), used by the language "%language%", is a page of terms and conditions, not a cookie policy: publish a page dedicated to cookies.', ['%language%' => $language, '%url%' => $url], self::DOMAIN),
                'ccm_admin_policy_template',
            );
        }

        $fallback = trim((string) ($draft['policy']['url'] ?? ''));
        if (!$fallbackUsed && '' !== $fallback && TermsPageUrl::matches($fallback)) {
            $alerts[] = new Alert(
                Alert::WARNING,
                'policy_url_terms_fallback',
                $this->translator->trans('The fallback address of the cookie policy (%url%) is a page of terms and conditions. No active language uses it today, but a language activated later would show it to its visitors: replace it with a page dedicated to cookies.', ['%url%' => $fallback], self::DOMAIN),
                'ccm_admin_policy_template',
            );
        }

        return $alerts;
    }
}
