<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\Alert;
use CookieConsentManager\Admin\EmbeddedMediaAlert;
use CookieConsentManager\Admin\FieldLabeler;
use CookieConsentManager\Catalogue\CatalogueReader;
use CookieConsentManager\Draft\DraftNormalizer;
use CookieConsentManager\Tests\Support\CatalogueTranslator;
use PHPUnit\Framework\TestCase;

final class EmbeddedMediaAlertTest extends TestCase
{
    /** @param array<string, mixed> $draft */
    private function alert(array $draft): ?Alert
    {
        $translator = new CatalogueTranslator('en_US');
        $labels = new FieldLabeler(new CatalogueReader(__DIR__.'/../../../Resources/catalogue/catalogue.json'), $translator);

        return (new EmbeddedMediaAlert($labels, $translator))->check((new DraftNormalizer())->normalize($draft), 'en');
    }

    public function testAnActiveVideoServiceWithTheBlockingOffIsNamedInARedAlert(): void
    {
        $alert = $this->alert(['general' => ['block_embedded_iframes' => false], 'services' => [['key' => 'youtube', 'purpose' => 'media', 'enabled' => true]]]);

        self::assertNotNull($alert);
        self::assertSame(Alert::DANGER, $alert->severity);
        self::assertStringContainsString('YouTube', $alert->message);
        self::assertStringContainsString('before the visitor consents', $alert->message);
    }

    public function testNothingIsRaisedWhenTheBlockingIsOnOrNoVideoServiceIsActive(): void
    {
        self::assertNull($this->alert(['services' => [['key' => 'youtube', 'purpose' => 'media', 'enabled' => true]]]), 'blocking on (default)');
        self::assertNull($this->alert(['general' => ['block_embedded_iframes' => false], 'services' => [['key' => 'youtube', 'purpose' => 'media', 'enabled' => false]]]), 'service disabled');
        self::assertNull($this->alert(['general' => ['block_embedded_iframes' => false], 'services' => [['key' => 'gtag', 'purpose' => 'analytics', 'enabled' => true, 'parameters' => ['gtagUa' => 'G-ABC123DEF4']]]]), 'no video');
    }
}
