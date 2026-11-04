<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Controller\ServedScriptController;
use CookieConsentManager\Front\AssembledScriptHasher;
use CookieConsentManager\Front\ConsentThemeHook;
use CookieConsentManager\Front\FooterLinkResolver;
use CookieConsentManager\Front\FrontConfigProvider;
use CookieConsentManager\Front\FrontTexts;
use CookieConsentManager\Front\PreviewToken;
use CookieConsentManager\Front\ServedHashResolver;
use CookieConsentManager\Front\ServedScriptAssembler;
use CookieConsentManager\Front\StyleSheet;
use CookieConsentManager\Front\TemplateContext;
use CookieConsentManager\Publication\ModuleVersion;
use CookieConsentManager\Tests\Support\CatalogueTranslator;
use CookieConsentManager\Tests\Support\CcmTestCase;
use CookieConsentManager\Tests\Support\ModuleRoutes;
use CookieConsentManager\Tests\Support\MutableOverrides;
use CookieConsentManager\Tests\Support\NoOverriddenTemplates;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Security\SecurityContext;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/** A shop installed in a sub-directory: every URL of the module carries the base path of the request, and none is frozen in what is cached. */
final class FrontBaseUrlTest extends CcmTestCase
{
    private string $module;
    private ServedHashResolver $hashes;
    private PreviewToken $tokens;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = \dirname(__DIR__, 3);
        $this->hashes = new ServedHashResolver($this->ccm->versions, new AssembledScriptHasher(new ServedScriptAssembler($this->module)), new ModuleVersion($this->module.'/Config/module.xml'), new NoOverriddenTemplates(), $this->ccm->pool);
        $this->tokens = new PreviewToken('test-secret');
    }

    private function provider(string $baseUrl, bool $admin = false): FrontConfigProvider
    {
        $security = $this->createStub(SecurityContext::class);
        $security->method('hasAdminUser')->willReturn($admin);

        return new FrontConfigProvider($this->ccm->versions, $this->ccm->builder, $this->ccm->assembler, $this->ccm->drafts, $this->tokens, $this->hashes, $security, $this->ccm->pointer, $this->ccm->pool, ModuleRoutes::urls($baseUrl));
    }

    private function publish(): int
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $this->ccm->publisher->publish('alice');

        return $this->ccm->versions->latest()->getVersion();
    }

    public function testTheConfigurationCarriesTheBasePathAndTheHashDoesNotDependOnIt(): void
    {
        $number = $this->publish();

        $shop = $this->provider('/shop')->forRequest(Request::create('/'))->config;
        self::assertSame('/shop/ccm/consent', $shop['endpoint']);
        self::assertSame(\sprintf('/shop/ccm/%d-%s.js', $number, $shop['hash']), $shop['script']);
        self::assertSame('/shop/ccm/preferences', $shop['preferencesUrl']);

        // Same application cache, other request context: nothing host-specific was stored.
        $root = $this->provider('')->forRequest(Request::create('/'))->config;
        self::assertSame('/ccm/consent', $root['endpoint']);
        self::assertSame(\sprintf('/ccm/%d-%s.js', $number, $root['hash']), $root['script']);
        self::assertSame($shop['hash'], $root['hash'], 'the served hash is the same for every base path');
        self::assertEquals(array_diff_key($shop, ['endpoint' => 1, 'script' => 1, 'preferencesUrl' => 1]), array_diff_key($root, ['endpoint' => 1, 'script' => 1, 'preferencesUrl' => 1]));
    }

    public function testThePreviewScriptCarriesTheBasePath(): void
    {
        $this->publish();
        $token = $this->tokens->forDraft($this->ccm->drafts->load());

        $preview = $this->provider('/shop', true)->forRequest(Request::create('/', 'GET', [PreviewToken::QUERY => $token]));

        self::assertSame('preview', $preview->mode());
        self::assertSame('/shop/ccm/preview-'.$token.'.js', $preview->config['script']);
    }

    public function testTheHeadTheFooterLinkAndTheTemplatesPointUnderTheBasePath(): void
    {
        $number = $this->publish();
        $configs = $this->provider('/shop');
        $request = Request::create('/fr/');
        $stack = new RequestStack();
        $stack->push($request);
        $texts = new FrontTexts(new CatalogueTranslator());
        $twig = new Environment(new FilesystemLoader([$this->module.'/templates/frontOffice/flexy']), ['strict_variables' => true]);
        $hook = new ConsentThemeHook($stack, $configs, new TemplateContext($texts), $texts, new StyleSheet($this->module.'/assets/css/ccm.css'), $twig, $this->module.'/assets/js/head.js');

        $head = $hook->render('layout.head.top', []);
        self::assertStringContainsString('"endpoint":"/shop/ccm/consent"', $head);
        self::assertMatchesRegularExpression(\sprintf('~<script defer src="/shop/ccm/%d-[0-9a-f]{12}\.js"></script>~', $number), $head);

        $variables = (new TemplateContext($texts))->build($configs->forRequest($request));
        self::assertStringContainsString('href="/shop/ccm/preferences"', $twig->render('CookieConsentManager/open-link.html.twig', $variables + ['label' => 'x']));
        $placeholder = $variables + ['service' => ['key' => 'youtube', 'name' => 'YouTube', 'purpose_label' => '', 'controller' => '', 'policy_url' => ''], 'attributes' => []];
        self::assertStringContainsString('href="/shop/ccm/preferences"', $twig->render('CookieConsentManager/placeholder.html.twig', $placeholder));

        if (interface_exists('Thelia\Core\Content\Slot\ContentSlotResolverInterface')) {
            $links = (new FooterLinkResolver($configs, new TemplateContext($texts), []))->resolve('footer_links', 'fr_FR');
            self::assertNotNull($links);
            self::assertSame('/shop/ccm/preferences#ccm-preferences', end($links)->url);
        }
    }

    public function testTheRedirectToTheCurrentScriptKeepsTheBasePath(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $version = $this->ccm->publisher->publish('alice');
        $script = new ServedScriptAssembler($this->module);
        $templates = new MutableOverrides();
        $hashes = new ServedHashResolver($this->ccm->versions, new AssembledScriptHasher($script), new ModuleVersion($this->module.'/Config/module.xml'), $templates, $this->ccm->pool);
        $controller = new ServedScriptController($this->ccm->versions, $hashes, $script, $this->ccm->assembler, $this->ccm->drafts, $this->tokens, ModuleRoutes::urls('/shop'));

        $redirect = $controller->script($version->getVersion(), str_repeat('0', 12));

        self::assertSame(\sprintf('/shop/ccm/%d-%s.js', $version->getVersion(), $hashes->forVersion($version)), $redirect->headers->get('Location'));
    }
}
