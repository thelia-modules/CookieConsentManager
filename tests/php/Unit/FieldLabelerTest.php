<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\FieldLabeler;
use CookieConsentManager\Catalogue\CatalogueReader;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FieldLabelerTest extends TestCase
{
    public function testTechnicalPathsBecomeReadableLabels(): void
    {
        $labels = new FieldLabeler(new CatalogueReader(__DIR__.'/../../../Resources/catalogue/catalogue.json'), new class implements TranslatorInterface {
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return $id;
            }

            public function getLocale(): string
            {
                return 'fr';
            }
        });
        $draft = ['purposes' => [['code' => 'analytics', 'label' => ['fr' => 'Mesure d\'audience', 'en' => 'Audience measurement']]]];

        self::assertSame('Crisp › Identifiant du site Crisp', $labels->label('services.crisp.parameters.crispID', 'fr', $draft));
        self::assertSame('Crisp › Crisp website ID', $labels->label('services.crisp.parameters.crispID', 'en', $draft));
        self::assertSame('Button color', $labels->label('appearance.colors.primary', 'fr'));
        self::assertSame('Banner text (FR) › Accept button', $labels->label('texts.fr.accept', 'fr'));
        self::assertSame('Mesure d\'audience › Label fr', $labels->label('purposes.analytics.label.fr', 'fr', $draft));
        self::assertSame('Mesure d\'audience › Label fr', $labels->label('purposes.0.label.fr', 'fr', $draft), 'a purpose of a diff is named by its position in the list');
        self::assertSame('Crisp › Enabled', $labels->label('services.crisp.enabled', 'fr', $draft));
        self::assertSame('unknown.path', $labels->label('unknown.path', 'fr'));
    }
}
