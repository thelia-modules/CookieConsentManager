<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Support;

use Thelia\Test\IntegrationTestCase;

abstract class CcmTestCase extends IntegrationTestCase
{
    protected Wiring $ccm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ccm = new Wiring();
        $this->ccm->drafts->ensure();
    }

    /** @return array<string, mixed> a draft with GA4 and YouTube on, ready to publish */
    protected function publishableDraft(): array
    {
        $draft = $this->ccm->drafts->load();
        $draft['policy']['url'] = '/politique-de-confidentialite';
        $draft['services'] = [
            ['key' => 'gtag', 'purpose' => 'analytics', 'enabled' => true, 'parameters' => ['gtagUa' => 'G-ABC123XYZ']],
            // Google documents no lifetime for VISITOR_PRIVACY_METADATA: the editor types the measured one (180 days)
            ['key' => 'youtube', 'purpose' => 'media', 'enabled' => true, 'parameters' => [], 'cookie_durations' => ['VISITOR_PRIVACY_METADATA' => 180]],
        ];

        return $draft;
    }

    protected function completeConfigJson(): string
    {
        return (string) file_get_contents(__DIR__.'/../Fixtures/complete-config.json');
    }
}
