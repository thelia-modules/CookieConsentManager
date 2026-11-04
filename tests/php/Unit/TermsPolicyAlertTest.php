<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\ActiveLanguages;
use CookieConsentManager\Admin\Alert;
use CookieConsentManager\Admin\TermsPolicyAlert;
use CookieConsentManager\Draft\DraftNormalizer;
use CookieConsentManager\Tests\Support\CatalogueTranslator;
use CookieConsentManager\Tests\Support\Wiring;
use PHPUnit\Framework\TestCase;

final class TermsPolicyAlertTest extends TestCase
{
    /** @param array<string, mixed> $draft */
    private function codes(array $draft): array
    {
        $languages = new class extends ActiveLanguages {
            public function mainCode(): string
            {
                return 'fr';
            }

            public function codes(): array
            {
                return ['fr', 'en'];
            }
        };
        $alerts = (new TermsPolicyAlert((new Wiring())->validator, $languages, new CatalogueTranslator()))->collect((new DraftNormalizer())->normalize($draft));
        foreach ($alerts as $alert) {
            self::assertSame(Alert::DANGER, $alert->severity);
        }

        return array_map(static fn (Alert $alert): string => $alert->code, $alerts);
    }

    public function testTheFallbackAddressOfTheTermsOfSaleRaisesAnAlertWithoutAnyPreset(): void
    {
        self::assertSame(['policy_url_terms_fr', 'policy_url_terms_en'], $this->codes(['policy' => ['url' => '/terms-and-conditions.html']]));
    }

    public function testAnAddressOfItsOwnPerLanguageIsCheckedAndTheLanguageWithAGoodOneIsLeftAlone(): void
    {
        self::assertSame(['policy_url_terms_en'], $this->codes(['policy' => ['url' => '/politique-de-cookies', 'urls' => ['en' => '/cgv']]]));
        self::assertSame([], $this->codes(['policy' => ['url' => '/terms-and-conditions.html', 'urls' => ['fr' => '/politique-de-cookies', 'en' => '/cookie-policy']]]), 'the fallback is unused');
    }

    public function testUnderTheFrenchPresetTheBlockingMessageIsNotDoubled(): void
    {
        self::assertSame([], $this->codes(['presets' => ['fr' => 'fr-FR'], 'policy' => ['url' => '/terms-and-conditions.html']]), 'every active language is refused by the validator');
        self::assertSame(['policy_url_terms_en'], $this->codes(['presets' => ['fr' => 'de-DE'], 'policy' => ['url' => '/terms-and-conditions.html', 'urls' => ['fr' => '/cookies']]]));
    }

    public function testAPresetOnAnotherLanguageLeavesTheMainOneToTheAlert(): void
    {
        self::assertSame(['policy_url_terms_fr'], $this->codes(['presets' => ['en' => 'fr-FR'], 'policy' => ['url' => '/terms-and-conditions.html', 'urls' => ['en' => '/cookie-policy']]]));
    }
}
