<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Front\AssembledScriptHasher;
use CookieConsentManager\Front\ConsentThemeHook;
use CookieConsentManager\Front\FrontConfig;
use CookieConsentManager\Front\FrontConfigProvider;
use CookieConsentManager\Front\FrontTexts;
use CookieConsentManager\Front\PreviewToken;
use CookieConsentManager\Front\ServedHashResolver;
use CookieConsentManager\Front\ServedScriptAssembler;
use CookieConsentManager\Front\StyleSheet;
use CookieConsentManager\Front\TemplateContext;
use CookieConsentManager\Publication\ModuleVersion;
use CookieConsentManager\Publication\Publisher;
use CookieConsentManager\Publication\SnapshotAssembler;
use CookieConsentManager\Tests\Support\CcmTestCase;
use CookieConsentManager\Tests\Support\NoOverriddenTemplates;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Security\SecurityContext;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/** The translator of Thelia, as booted by the kernel, serves the front in every language of the module. */
final class FrontLanguageTest extends CcmTestCase
{
    private const ENGLISH = ['Your cookie choices', 'Accept all', 'Reject all', 'Customize', 'Audience measurement', 'Advertising', 'Cookie preferences', 'Save my choices', 'Always on', 'Required for the site to work'];

    /** @return iterable<string, array{string}> */
    public static function languages(): iterable
    {
        foreach (['fr', 'de', 'nl', 'es'] as $language) {
            yield $language => [$language];
        }
    }

    #[DataProvider('languages')]
    public function testABannerWithoutTypedTextIsEntirelyInTheLanguageOfTheVisitor(string $language): void
    {
        $front = $this->published();
        $locale = FrontTexts::LOCALES[$language];
        $translator = static::getContainer()->get(TranslatorInterface::class);
        $texts = new FrontTexts($translator);
        $variables = (new TemplateContext($texts))->build(new FrontConfig(['locale' => $locale] + $front->config, $this->withoutTexts($front->snapshot, $language)));

        $twig = new Environment(new FilesystemLoader([\dirname(__DIR__, 3).'/templates/frontOffice/flexy']), ['strict_variables' => true]);
        $html = $twig->render('CookieConsentManager/banner.html.twig', $variables).$twig->render('CookieConsentManager/preferences-panel.html.twig', $variables);

        foreach (self::ENGLISH as $english) {
            self::assertStringNotContainsString($english, $html, $language.' shows English "'.$english.'"');
        }
        $defaults = $texts->defaultTexts($language);
        self::assertStringContainsString($defaults['title'], $html);
        self::assertStringContainsString($defaults['reject'], $html);
        self::assertStringContainsString($defaults['accept'], $html);
        self::assertSame($defaults['title'], $variables['texts']['title']);
        self::assertSame($texts->defaultPurpose('analytics', $language)['label'], $variables['purposes'][0]['label']);
        self::assertSame($translator->trans('Save my choices', [], FrontTexts::DOMAIN, $locale), $variables['texts']['save']);
        self::assertNotSame('Reject all', $variables['texts']['reject']);
    }

    public function testTheGermanBannerIsInGerman(): void
    {
        $front = $this->published();
        $texts = new FrontTexts(static::getContainer()->get(TranslatorInterface::class));
        $variables = (new TemplateContext($texts))->build(new FrontConfig(['locale' => 'de_DE'] + $front->config, $this->withoutTexts($front->snapshot, 'de')));

        self::assertSame('Ihre Cookie-Auswahl', $variables['texts']['title']);
        self::assertSame('Alle ablehnen', $variables['texts']['reject']);
        self::assertSame('Alle akzeptieren', $variables['texts']['accept']);
        self::assertSame('Reichweitenmessung', $variables['purposes'][0]['label']);
    }

    public function testALanguageWithoutCatalogueFallsBackToTheTypedOrEnglishTexts(): void
    {
        $front = $this->published();
        $texts = new FrontTexts(static::getContainer()->get(TranslatorInterface::class));
        $variables = (new TemplateContext($texts))->build(new FrontConfig(['locale' => 'it_IT'] + $front->config, $front->snapshot));

        self::assertSame('Reject all', $variables['texts']['reject']);
        self::assertSame('Cookie preferences', $variables['texts']['panel_title']);
    }

    public function testThePageCarriesTheMessagesOfTheEngineInTheLanguageOfTheVisitor(): void
    {
        $this->published();
        $module = \dirname(__DIR__, 3);
        $stack = new RequestStack();
        $stack->push(Request::create('/'));
        $translator = static::getContainer()->get(TranslatorInterface::class);
        $texts = new FrontTexts($translator);
        $moduleVersion = new ModuleVersion($module.'/Config/module.xml');
        $hashes = new ServedHashResolver($this->ccm->versions, new AssembledScriptHasher(new ServedScriptAssembler($module)), $moduleVersion, new NoOverriddenTemplates(), $this->ccm->pool);
        $security = $this->createStub(SecurityContext::class);
        $security->method('hasAdminUser')->willReturn(true);
        $configs = new FrontConfigProvider($this->ccm->versions, $this->ccm->builder, $this->ccm->assembler, $this->ccm->drafts, new PreviewToken('test-secret'), $hashes, $security, $this->ccm->pointer, $this->ccm->pool, $this->ccm->urls);
        $twig = new Environment(new FilesystemLoader([$module.'/templates/frontOffice/flexy']), ['strict_variables' => true]);
        $hook = new ConsentThemeHook($stack, $configs, new TemplateContext($texts), $texts, new StyleSheet($module.'/assets/css/ccm.css'), $twig, $module.'/assets/js/head.js');

        $body = $hook->render('layout.body.top', []);
        self::assertSame(1, preg_match('~<script type="application/json" id="ccm-messages">(.*?)</script>~s', $body, $found));
        $messages = json_decode($found[1], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(array_keys($texts->messages('en')), array_keys($messages));
        self::assertStringContainsString('{{name}}', $messages['blocked']);
        self::assertStringContainsString('{{name}}', $messages['allow']);
    }

    /**
     * @param array<string, mixed> $snapshot
     *
     * @return array<string, mixed>
     */
    private function withoutTexts(array $snapshot, string $language): array
    {
        unset($snapshot['config']['texts'][$language]);
        foreach ($snapshot['config']['purposes'] as $index => $purpose) {
            unset($snapshot['config']['purposes'][$index]['label'][$language], $snapshot['config']['purposes'][$index]['description'][$language]);
        }

        return $snapshot;
    }

    private function published(): FrontConfig
    {
        $module = \dirname(__DIR__, 3);
        $moduleVersion = new ModuleVersion($module.'/Config/module.xml');
        $hasher = new AssembledScriptHasher(new ServedScriptAssembler($module));
        $templates = new NoOverriddenTemplates();
        $hashes = new ServedHashResolver($this->ccm->versions, $hasher, $moduleVersion, $templates, $this->ccm->pool);
        $publisher = new Publisher($this->ccm->drafts, $this->ccm->validator, new SnapshotAssembler($this->ccm->catalogue, $moduleVersion, $templates), $hasher, $this->ccm->versions, $this->ccm->cache, $moduleVersion, $this->ccm->pointer);
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $publisher->publish('alice');

        $security = $this->createStub(SecurityContext::class);
        $security->method('hasAdminUser')->willReturn(true);
        $front = (new FrontConfigProvider($this->ccm->versions, $this->ccm->builder, $this->ccm->assembler, $this->ccm->drafts, new PreviewToken('test-secret'), $hashes, $security, $this->ccm->pointer, $this->ccm->pool, $this->ccm->urls))->forRequest(Request::create('/'));
        self::assertNotNull($front);

        return $front;
    }
}
