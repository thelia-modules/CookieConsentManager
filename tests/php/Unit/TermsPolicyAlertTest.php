<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\ActiveLanguages;
use CookieConsentManager\Admin\Alert;
use CookieConsentManager\Admin\TermsPolicyAlert;
use CookieConsentManager\Draft\DraftNormalizer;
use CookieConsentManager\Tests\Support\CatalogueTranslator;
use CookieConsentManager\Validation\FrenchPresetLanguages;
use PHPUnit\Framework\TestCase;

final class TermsPolicyAlertTest extends TestCase
{
    /** @param array<string, mixed> $draft */
    private function codes(array $draft): array
    {
        return array_map(static fn (Alert $alert): string => $alert->code, $this->alerts($draft));
    }

    /**
     * @param array<string, mixed> $draft
     *
     * @return list<Alert>
     */
    private function alerts(array $draft): array
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

        return (new TermsPolicyAlert(new FrenchPresetLanguages($languages), $languages, new CatalogueTranslator()))->collect((new DraftNormalizer())->normalize($draft));
    }

    public function testTheFallbackAddressOfTheTermsOfSaleRaisesAnAlertWithoutAnyPreset(): void
    {
        self::assertSame(['policy_url_terms_fr', 'policy_url_terms_en'], $this->codes(['policy' => ['url' => '/terms-and-conditions.html']]));
    }

    public function testAnAddressOfItsOwnPerLanguageIsCheckedAndTheLanguageWithAGoodOneIsLeftAlone(): void
    {
        self::assertSame(['policy_url_terms_en'], $this->codes(['policy' => ['url' => '/politique-de-cookies', 'urls' => ['en' => '/cgv']]]));
        self::assertSame(Alert::DANGER, $this->alerts(['policy' => ['url' => '/politique-de-cookies', 'urls' => ['en' => '/cgv']]])[0]->severity);
    }

    public function testAnUnusedFallbackOfTheTermsOfSaleRaisesAWarningForTheLanguagesActivatedLater(): void
    {
        $alerts = $this->alerts(['policy' => ['url' => '/terms-and-conditions.html', 'urls' => ['fr' => '/politique-de-cookies', 'en' => '/cookie-policy']]]);
        self::assertSame(['policy_url_terms_fallback'], array_map(static fn (Alert $alert): string => $alert->code, $alerts));
        self::assertSame(Alert::WARNING, $alerts[0]->severity);
        self::assertStringContainsString('/terms-and-conditions.html', $alerts[0]->message);
        self::assertSame([], $this->codes(['policy' => ['url' => '/politique-de-cookies', 'urls' => ['fr' => '/politique', 'en' => '/cookie-policy']]]), 'a fallback about cookies');
        self::assertSame([], $this->codes(['policy' => ['urls' => ['fr' => '/politique', 'en' => '/cookie-policy']]]), 'no fallback');
    }

    public function testAFallbackUsedByALanguageRefusedByTheValidatorRaisesNoWarning(): void
    {
        self::assertSame([], $this->codes(['presets' => ['fr' => 'fr-FR'], 'policy' => ['url' => '/terms-and-conditions.html', 'urls' => ['en' => '/cookie-policy']]]));
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
