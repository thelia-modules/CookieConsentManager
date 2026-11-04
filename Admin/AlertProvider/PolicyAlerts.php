<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin\AlertProvider;

use CookieConsentManager\Admin\Alert;
use CookieConsentManager\Admin\PolicyUrlAlert;
use CookieConsentManager\Admin\TermsPolicyAlert;
use CookieConsentManager\Front\TemplateContext;
use CookieConsentManager\Publication\VersionRepository;

/** The published privacy policy address must answer 200; a terms page is no cookie policy. */
final readonly class PolicyAlerts implements AlertProvider
{
    public function __construct(
        private VersionRepository $versions,
        private PolicyUrlAlert $policy,
        private TermsPolicyAlert $termsPolicy,
    ) {
    }

    public static function priority(): int
    {
        return 900;
    }

    public function alerts(array $draft): array
    {
        $alerts = [];

        $published = null === ($latest = $this->versions->latest()) ? $draft['policy'] : ($this->versions->snapshot($latest)['config']['policy'] ?? []);
        foreach ($this->policyUrls($published, array_keys($draft['presets'])) as $suffix => $policyUrl) {
            if (null !== $policyAlert = $this->policy->check($policyUrl, $suffix)) {
                $alerts[] = $policyAlert;
            }
        }

        array_push($alerts, ...$this->termsPolicy->collect($draft));

        return $alerts;
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
}
