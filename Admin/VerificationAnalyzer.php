<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

/**
 * Turns the raw readings of the audited pages into a verdict per domain and per cookie.
 * The readings come from the page itself (the script of the Verification screen); the services
 * and their domains come from the published snapshot, never from the draft.
 */
final class VerificationAnalyzer
{
    public const BEFORE = 'before';
    public const AFTER_REJECT = 'after_reject';
    public const KO = 'ko';
    public const TODO = 'todo';
    public const WARN = 'warn';
    public const OK = 'ok';
    public const ALLOWED_COOKIE_PATTERNS = ['PHPSESSID', 'ccm_*', 'thelia_*', 'main_*_profile_token'];
    public const LIMITS = ['httponly_cookies', 'iframe_subresources', 'resource_timing', 'checklist'];

    public const MANAGED = 'managed';
    public const OUTSIDE = 'outside';
    public const FONTS = 'fonts';

    private const SEVERITY = [self::OK => 0, self::WARN => 1, self::TODO => 2, self::KO => 3];
    private const EXAMPLES = 3;
    private const FONT_HOSTS = ['fonts.googleapis.com', 'fonts.gstatic.com'];

    /**
     * @param list<array<string, mixed>> $reports  one reading per audited page and phase
     * @param array<string, mixed>       $snapshot snapshot of the published version
     *
     * @return array<string, mixed>
     */
    public function analyze(array $reports, array $snapshot, string $siteHost, ?int $publishedVersion = null): array
    {
        $exemptKeys = array_column((array) ($snapshot['config']['exempt_measurement'] ?? []), 'service');
        $services = [];
        foreach ($snapshot['services_resolved'] ?? [] as $service) {
            $exempt = \in_array($service['key'], $exemptKeys, true);
            $service['exempt'] = $exempt;
            $service['domains'] = self::serviceDomains($service, $exempt);
            $services[] = $service;
        }
        $advanced = 'advanced' === ($snapshot['config']['consent_mode']['mode'] ?? 'basic');
        $domains = [];
        $cookies = [];
        $alerts = [];

        foreach ($reports as $report) {
            $phase = self::BEFORE === ($report['phase'] ?? null) ? self::BEFORE : self::AFTER_REJECT;
            $url = self::cleanUrl((string) ($report['url'] ?? ''));
            $hardcodedGoogle = $this->hardcodedGoogleTag($report, $services);

            foreach ($this->hosts($report) as $host) {
                if ($this->isOwn($host, $siteHost)) {
                    continue;
                }
                $service = $this->serviceForHost($host, $services);
                $reason = null === $service ? 'unknown_domain' : null;
                if (null !== $service && $hardcodedGoogle && ($service['google_tag'] ?? false)) {
                    // The module never loads a Google tag before the choice: the page loads its own.
                    $service = null;
                    $reason = 'google_tag_hardcoded';
                }
                if (\in_array($host, self::FONT_HOSTS, true)) {
                    $service = null;
                    $reason = 'theme_fonts';
                }
                $origin = null !== $service ? self::MANAGED : ('theme_fonts' === $reason ? self::FONTS : self::OUTSIDE);
                $verdict = null === $service ? self::TODO : $this->verdict($service, $phase, $advanced);
                $domains[$host] ??= ['host' => $host, 'service' => $service['key'] ?? null, 'name' => $service['name'] ?? null, 'origin' => $origin, 'reason' => $reason, 'verdict' => self::OK, 'seen' => []];
                $domains[$host]['verdict'] = $this->worst($domains[$host]['verdict'], $verdict);
                $domains[$host]['seen'][] = ['url' => $url, 'phase' => $phase, 'verdict' => $verdict];
            }

            foreach ((array) ($report['cookies'] ?? []) as $name) {
                $name = (string) $name;
                if ($this->matchesAny($name, self::ALLOWED_COOKIE_PATTERNS)) {
                    continue;
                }
                $service = $this->serviceForCookie($name, $services);
                $verdict = null === $service ? self::TODO : $this->verdict($service, $phase, $advanced);
                $cookies[$name] ??= ['name' => $name, 'service' => $service['key'] ?? null, 'verdict' => self::OK, 'seen' => []];
                $cookies[$name]['verdict'] = $this->worst($cookies[$name]['verdict'], $verdict);
                $cookies[$name]['seen'][] = ['url' => $url, 'phase' => $phase, 'verdict' => $verdict];
            }

            $tags = (array) ($report['google_tags'] ?? []);
            if ([] !== ($tags['gtag_js'] ?? []) && [] !== ($tags['gtm_js'] ?? [])) {
                $alerts['gtag_twice'] = ['code' => 'gtag_twice', 'url' => $url];
            }
            if ([] !== ($tags['gtm_js'] ?? []) && [] === ($tags['gtag_js'] ?? []) && !$this->hasService($services, 'googletagmanager') && [] !== $this->googleServices($services)) {
                $alerts['gtag_theme_and_module'] = ['code' => 'gtag_theme_and_module', 'url' => $url, 'services' => implode(', ', $this->googleServices($services))];
            }
            if (null !== $publishedVersion && isset($report['version']) && (int) $report['version'] !== $publishedVersion) {
                $alerts['version_mismatch'] = ['code' => 'version_mismatch', 'url' => $url, 'served' => (int) $report['version'], 'published' => $publishedVersion];
            }
        }

        $domains = array_map($this->withPages(...), array_values($domains));
        $cookies = array_map($this->withPages(...), array_values($cookies));
        usort($domains, static fn (array $a, array $b): int => [self::SEVERITY[$b['verdict']], $a['host']] <=> [self::SEVERITY[$a['verdict']], $b['host']]);

        $origins = array_fill_keys([self::MANAGED, self::OUTSIDE, self::FONTS], 0);
        foreach ($domains as $domain) {
            ++$origins[$domain['origin']];
        }

        $summary = array_fill_keys(array_keys(self::SEVERITY), 0);
        foreach ([...$domains, ...$cookies] as $item) {
            ++$summary[$item['verdict']];
        }

        return [
            'domains' => $domains,
            'cookies' => $cookies,
            'alerts' => array_values($alerts),
            'summary' => $summary,
            'origins' => $origins,
            'limits' => self::LIMITS,
        ];
    }

    /**
     * The domains a service of the module contacts: the catalogue ones, plus the host the
     * administrator typed (instance URL, script address) when the service is exempt or
     * strictly necessary, since the module runs it before any choice, and the host of the
     * script of a custom service.
     *
     * @param array<string, mixed> $service
     *
     * @return list<string>
     */
    public static function serviceDomains(array $service, bool $exempt): array
    {
        $domains = array_map('strval', (array) ($service['domains'] ?? []));
        if ($exempt || 'essential' === ($service['purpose'] ?? null) || isset($service['script_url'])) {
            $candidates = [...array_values((array) ($service['user'] ?? [])), $service['script_url'] ?? null];
            foreach ($candidates as $value) {
                if (\is_string($value) && 1 === preg_match('~^(https?:)?//~i', $value) && \is_string($host = parse_url($value, \PHP_URL_HOST)) && '' !== $host) {
                    $domains[] = strtolower($host);
                }
            }
        }

        return array_values(array_unique($domains));
    }

    /**
     * One line per domain or cookie: the number of distinct pages per phase, and a few examples.
     *
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function withPages(array $item): array
    {
        $pages = [self::BEFORE => [], self::AFTER_REJECT => []];
        foreach ($item['seen'] as $seen) {
            $pages[$seen['phase']][$seen['url']] = true;
        }
        $item['pages'] = [];
        foreach ($pages as $phase => $urls) {
            $item['pages'][$phase] = ['count' => \count($urls), 'examples' => \array_slice(array_keys($urls), 0, self::EXAMPLES)];
        }

        return $item;
    }

    /** The audit token of the page address is a credential: it never leaves the analysis. */
    public static function cleanUrl(string $url): string
    {
        $clean = preg_replace('/([?&])ccm_audit(?:_phase)?=[^&#]*(&?)/', '$1', $url) ?? $url;
        $clean = preg_replace('/([?&])ccm_audit(?:_phase)?=[^&#]*(&?)/', '$1', $clean) ?? $clean;

        return rtrim($clean, '?&');
    }

    /**
     * A Google tag container read on the page while no service of the module loads it.
     *
     * @param array<string, mixed>       $report
     * @param list<array<string, mixed>> $services
     */
    private function hardcodedGoogleTag(array $report, array $services): bool
    {
        $tags = (array) ($report['google_tags'] ?? []);
        if ([] !== ($tags['gtm_js'] ?? []) && [] === ($tags['gtag_js'] ?? []) && !$this->hasService($services, 'googletagmanager')) {
            return true;
        }

        $configured = [];
        foreach ($this->googleServices($services, true) as $service) {
            foreach ((array) ($service['user'] ?? []) as $value) {
                $configured[] = (string) $value;
            }
        }
        foreach ((array) ($tags['gtag_js'] ?? []) as $id) {
            if (!\in_array((string) $id, $configured, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array<string, mixed>> $services */
    private function hasService(array $services, string $key): bool
    {
        return \in_array($key, array_column($services, 'key'), true);
    }

    /**
     * @param list<array<string, mixed>> $services
     *
     * @return list<array<string, mixed>>|list<string>
     */
    private function googleServices(array $services, bool $full = false): array
    {
        $google = array_values(array_filter($services, static fn (array $service): bool => ($service['google_tag'] ?? false) && 'googletagmanager' !== $service['key']));

        return $full ? $google : array_map(static fn (array $service): string => (string) ($service['name'] ?? $service['key']), $google);
    }

    /**
     * @param array<string, mixed> $report
     *
     * @return list<string>
     */
    private function hosts(array $report): array
    {
        $hosts = array_map('strval', (array) ($report['hosts'] ?? []));
        foreach ((array) ($report['iframes'] ?? []) as $src) {
            $host = parse_url((string) $src, \PHP_URL_HOST);
            if (\is_string($host) && '' !== $host) {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique(array_map('strtolower', array_filter($hosts, static fn (string $host): bool => '' !== $host))));
    }

    private function isOwn(string $host, string $siteHost): bool
    {
        $siteHost = strtolower($siteHost);

        return $host === $siteHost || str_ends_with($host, '.'.$siteHost);
    }

    /**
     * @param array<string, mixed> $service
     */
    private function verdict(array $service, string $phase, bool $advanced): string
    {
        if ('essential' === $service['purpose'] || ($service['exempt'] ?? false)) {
            return self::OK;
        }
        if (self::BEFORE === $phase && $advanced && ($service['google_tag'] ?? false)) {
            return self::WARN;
        }

        return self::KO;
    }

    /**
     * @param list<array<string, mixed>> $services
     *
     * @return array<string, mixed>|null
     */
    private function serviceForHost(string $host, array $services): ?array
    {
        foreach ($services as $service) {
            foreach ((array) ($service['domains'] ?? []) as $domain) {
                $domain = strtolower((string) $domain);
                if (str_starts_with($domain, '*.') ? str_ends_with($host, substr($domain, 1)) : ($host === $domain || str_ends_with($host, '.'.$domain))) {
                    return $service;
                }
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $services
     *
     * @return array<string, mixed>|null
     */
    private function serviceForCookie(string $name, array $services): ?array
    {
        foreach ($services as $service) {
            if ($this->matchesAny($name, array_map('strval', (array) ($service['cookies'] ?? [])))) {
                return $service;
            }
        }

        return null;
    }

    /** @param list<string> $patterns names, or patterns where `*` stands for any characters */
    private function matchesAny(string $name, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (1 === preg_match('/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/D', $name)) {
                return true;
            }
        }

        return false;
    }

    private function worst(string $current, string $candidate): string
    {
        return self::SEVERITY[$candidate] > self::SEVERITY[$current] ? $candidate : $current;
    }
}
