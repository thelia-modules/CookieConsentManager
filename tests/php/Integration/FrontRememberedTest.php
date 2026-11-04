<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Front\FrontConfig;
use CookieConsentManager\Front\FrontTexts;
use CookieConsentManager\Front\TemplateContext;
use CookieConsentManager\Tests\Support\CatalogueTranslator;
use CookieConsentManager\Tests\Support\CcmTestCase;

/** R4 #2: the panel says for how long the choice is remembered (the renewal delay). */
final class FrontRememberedTest extends CcmTestCase
{
    public function testThePanelSaysHowLongTheChoiceIsRemembered(): void
    {
        $draft = $this->publishableDraft();
        $draft['general']['renewal_months'] = 6;
        $snapshot = ['config' => $this->ccm->normalizer->normalize($draft), 'services_resolved' => []];
        $front = new FrontConfig(['locale' => 'fr_FR', 'exempt' => [], 'purposes' => [], 'mode' => 'live'], $snapshot);

        $context = (new TemplateContext(new FrontTexts(new CatalogueTranslator())))->build($front);
        self::assertSame('Votre choix est mémorisé sur cet appareil pendant 6 mois.', $context['texts']['remembered']);

        $snapshot['config']['general']['renewal_months'] = 1;
        $one = (new TemplateContext(new FrontTexts(new CatalogueTranslator())))->build(new FrontConfig($front->config, $snapshot));
        self::assertSame('Votre choix est mémorisé sur cet appareil pendant 1 mois.', $one['texts']['remembered']);
    }
}
