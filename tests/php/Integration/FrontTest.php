<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Controller\PreferencesPageController;
use CookieConsentManager\Controller\ServedScriptController;
use CookieConsentManager\Front\AssembledScriptHasher;
use CookieConsentManager\Front\ConsentThemeHook;
use CookieConsentManager\Front\ConsentTwigExtension;
use CookieConsentManager\Front\FrontConfigProvider;
use CookieConsentManager\Front\FrontTexts;
use CookieConsentManager\Front\FrontThemeActivator;
use CookieConsentManager\Front\IframeRewriteListener;
use CookieConsentManager\Front\IframeRewriter;
use CookieConsentManager\Front\PlaceholderRenderer;
use CookieConsentManager\Front\PreviewToken;
use CookieConsentManager\Front\ServedHashResolver;
use CookieConsentManager\Front\ServedScriptAssembler;
use CookieConsentManager\Front\StyleSheet;
use CookieConsentManager\Front\TemplateContext;
use CookieConsentManager\Publication\ModuleVersion;
use CookieConsentManager\Publication\NoOverriddenTemplates;
use CookieConsentManager\Publication\Publisher;
use CookieConsentManager\Publication\SnapshotAssembler;
use CookieConsentManager\Registry\ConsentCookie;
use CookieConsentManager\Registry\RequestConsentReader;
use CookieConsentManager\Tests\Support\CatalogueTranslator;
use CookieConsentManager\Tests\Support\CcmTestCase;
use CookieConsentManager\Tests\Support\MutableOverrides;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Core\Security\SecurityContext;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class FrontTest extends CcmTestCase
{
    private string $module;
    private ServedScriptAssembler $script;
    private ServedHashResolver $hashes;
    private Publisher $publisher;
    private PreviewToken $tokens;
    private FrontConfigProvider $configs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = \dirname(__DIR__, 3);
        $moduleVersion = new ModuleVersion($this->module.'/Config/module.xml');
        $this->script = new ServedScriptAssembler($this->module);
        $hasher = new AssembledScriptHasher($this->script);
        $templates = new NoOverriddenTemplates();
        $this->hashes = new ServedHashResolver($this->ccm->versions, $hasher, $moduleVersion, $templates, $this->ccm->pool);
        $this->publisher = new Publisher($this->ccm->drafts, $this->ccm->validator, new SnapshotAssembler($this->ccm->catalogue, $moduleVersion, $templates), $hasher, $this->ccm->versions, $this->ccm->cache, $moduleVersion, $this->ccm->pointer);
        $this->tokens = new PreviewToken('test-secret');
        $this->configs = new FrontConfigProvider($this->ccm->versions, $this->ccm->builder, $this->ccm->assembler, $this->ccm->drafts, $this->tokens, $this->hashes, $this->security(true), $this->ccm->pointer, $this->ccm->pool);
    }

    private function security(bool $admin): SecurityContext
    {
        $security = $this->createStub(SecurityContext::class);
        $security->method('hasAdminUser')->willReturn($admin);

        return $security;
    }

    private function controller(): ServedScriptController
    {
        return new ServedScriptController($this->ccm->versions, $this->hashes, $this->script, $this->ccm->assembler, $this->ccm->drafts, $this->tokens);
    }

    private function twig(string $theme): Environment
    {
        return new Environment(new FilesystemLoader([$this->module.'/templates/frontOffice/'.$theme]), ['strict_variables' => true]);
    }

    private function extension(Request $request, string $theme = 'flexy'): ConsentTwigExtension
    {
        $stack = new RequestStack();
        $stack->push($request);
        $context = new TemplateContext(new FrontTexts(new CatalogueTranslator()));
        $twig = $this->twig($theme);

        return new ConsentTwigExtension($stack, new RequestConsentReader(), $this->configs, new PlaceholderRenderer($context, $twig), $context, $twig);
    }

    private function consentCookie(): string
    {
        return ConsentCookie::forChoice('4f1c2b9e-8d7a-4c3e-9b1f-2a6d5e7c8b90', 1, time(), ['analytics' => 1, 'media' => 0], ['gtag' => 1, 'youtube' => 0], [], true)->getValue();
    }

    public function testNothingIsRenderedBeforeThePublicationExceptForThePreview(): void
    {
        self::assertNull($this->configs->forRequest(Request::create('/')));

        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        self::assertNull($this->configs->forRequest(Request::create('/')), 'a saved draft is not published');

        $token = $this->tokens->forDraft($this->ccm->drafts->load());
        $preview = (new FrontConfigProvider($this->ccm->versions, $this->ccm->builder, $this->ccm->assembler, $this->ccm->drafts, $this->tokens, $this->hashes, $this->security(true), $this->ccm->pointer, $this->ccm->pool))
            ->forRequest(Request::create('/', 'GET', [PreviewToken::QUERY => $token]));
        self::assertSame('preview', $preview?->mode());
        self::assertSame('/ccm/preview-'.$token.'.js', $preview->config['script']);

        $wrong = (new FrontConfigProvider($this->ccm->versions, $this->ccm->builder, $this->ccm->assembler, $this->ccm->drafts, $this->tokens, $this->hashes, $this->security(true), $this->ccm->pointer, $this->ccm->pool))
            ->forRequest(Request::create('/', 'GET', [PreviewToken::QUERY => str_repeat('a', 32)]));
        self::assertNull($wrong);
    }

    public function testTheServedScriptIsVersionedImmutableAndNotFoundForAnUnknownVersion(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $version = $this->publisher->publish('alice');
        $config = $this->configs->forRequest(Request::create('/'))->config;

        self::assertSame('live', $config['mode']);
        self::assertSame(\sprintf('/ccm/%d-%s.js', $version->getVersion(), $config['hash']), $config['script']);
        self::assertSame($version->getServedHash(), $config['hash'], 'nothing changed since the publication: the live hash is the stored one');

        $response = $this->controller()->script($version->getVersion(), $config['hash']);
        self::assertTrue($response->headers->hasCacheControlDirective('public') && $response->headers->hasCacheControlDirective('immutable'));
        self::assertSame('31536000', $response->headers->getCacheControlDirective('max-age'));
        self::assertStringStartsWith('text/javascript', (string) $response->headers->get('Content-Type'));
        self::assertSame($this->script->assemble($this->ccm->versions->snapshot($version)), $response->getContent());
        self::assertStringContainsString('tarteaucitron.services.gtag', (string) $response->getContent());

        $this->expectException(NotFoundHttpException::class);
        $this->controller()->script($version->getVersion() + 1, $config['hash']);
    }

    public function testAnOutdatedHashOfAnExistingVersionRedirectsToTheCurrentScript(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $version = $this->publisher->publish('alice');
        $number = $version->getVersion();

        // The theme gets an override after the page was cached: the served hash of the same version changes.
        $templates = new MutableOverrides();
        $hashes = new ServedHashResolver($this->ccm->versions, new AssembledScriptHasher($this->script), new ModuleVersion($this->module.'/Config/module.xml'), $templates, $this->ccm->pool);
        $controller = new ServedScriptController($this->ccm->versions, $hashes, $this->script, $this->ccm->assembler, $this->ccm->drafts, $this->tokens);
        $previous = $hashes->forVersion($version);

        $templates->overridden = true;
        $hashes->reset();
        $current = $hashes->forVersion($version);
        self::assertNotSame($previous, $current);

        $redirect = $controller->script($number, $previous);
        self::assertSame(302, $redirect->getStatusCode());
        self::assertSame(\sprintf('/ccm/%d-%s.js', $number, $current), $redirect->headers->get('Location'));
        self::assertSame('60', $redirect->headers->getCacheControlDirective('max-age'));
        self::assertFalse($redirect->headers->hasCacheControlDirective('immutable'));

        self::assertSame(200, $controller->script($number, $current)->getStatusCode());

        try {
            $controller->script($number + 1, $previous);
            self::fail('404 expected for a version that does not exist');
        } catch (NotFoundHttpException) {
            self::addToAssertionCount(1);
        }
    }

    private function withProof(string $token): Request
    {
        $request = Request::create('/ccm/preview-'.$token.'.js');
        $request->cookies->set(PreviewToken::PROOF_COOKIE, $this->tokens->proofFor($token));

        return $request;
    }

    public function testThePreviewIsReservedToAConnectedAdministrator(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $this->publisher->publish('alice');
        $token = $this->tokens->forDraft($this->ccm->drafts->load());
        $anonymous = new FrontConfigProvider($this->ccm->versions, $this->ccm->builder, $this->ccm->assembler, $this->ccm->drafts, $this->tokens, $this->hashes, $this->security(false), $this->ccm->pointer, $this->ccm->pool);

        $request = Request::create('/', 'GET', [PreviewToken::QUERY => $token]);
        self::assertSame('live', $anonymous->forRequest($request)?->mode(), 'a visitor holding the token gets the normal rendering');
        self::assertNull($request->attributes->get(PreviewToken::PROOF_ATTRIBUTE));

        $admin = Request::create('/', 'GET', [PreviewToken::QUERY => $token]);
        self::assertSame('preview', $this->configs->forRequest($admin)?->mode());
        self::assertSame($token, $admin->attributes->get(PreviewToken::PROOF_ATTRIBUTE));
    }

    public function testThePreviewScriptNeedsTheProofOfAnAdministratorPage(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $token = $this->tokens->forDraft($this->ccm->drafts->load());

        foreach ([Request::create('/'), $this->withForeignProof($token)] as $request) {
            try {
                $this->controller()->preview($request, $token);
                self::fail('404 expected: the token alone must not give the draft');
            } catch (NotFoundHttpException) {
                self::addToAssertionCount(1);
            }
        }
    }

    private function withForeignProof(string $token): Request
    {
        $request = Request::create('/');
        $request->cookies->set(PreviewToken::PROOF_COOKIE, $this->tokens->proofFor(str_repeat('c', 32)));

        return $request;
    }

    public function testThePreviewScriptIsNeverCachedAndNeedsTheToken(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $token = $this->tokens->forDraft($this->ccm->drafts->load());

        $response = $this->controller()->preview($this->withProof($token), $token);
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertStringContainsString('CookieConsentManager.__start();', (string) $response->getContent());

        $this->expectException(NotFoundHttpException::class);
        $this->controller()->preview($this->withProof(str_repeat('b', 32)), str_repeat('b', 32));
    }

    public function testTwigHelpersReadTheCookieAndAreNeutralInAudit(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $this->publisher->publish('alice');

        $request = Request::create('/');
        $request->cookies->set(ConsentCookie::NAME, $this->consentCookie());
        $helpers = $this->extension($request);

        self::assertTrue($helpers->hasConsent('gtag'));
        self::assertFalse($helpers->hasConsent('youtube'));
        self::assertFalse($helpers->hasConsent('unknown'));
        self::assertTrue($helpers->hasPurposeConsent('analytics'));
        self::assertFalse($helpers->hasPurposeConsent('media'));

        $audit = Request::create('/', 'GET', [FrontConfigProvider::AUDIT_QUERY => $this->tokens->forDraft($this->ccm->drafts->load())]);
        $audit->cookies->set(ConsentCookie::NAME, $this->consentCookie());
        self::assertFalse($this->extension($audit)->hasConsent('gtag'), 'the audit never reads the choice');
        self::assertFalse($this->extension($audit)->hasPurposeConsent('analytics'));
    }

    public function testTwigHelpersIgnoreAnExpiredChoiceAndOneOlderThanTheLastSubstantialVersion(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $this->publisher->publish('alice');
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $this->publisher->publish('alice', true);
        $front = $this->configs->forRequest(Request::create('/'));
        self::assertSame(2, $front->config['substantialVersion']);

        $read = function (int $age, int $version): array {
            $request = Request::create('/');
            $request->cookies->set(ConsentCookie::NAME, ConsentCookie::forChoice('4f1c2b9e-8d7a-4c3e-9b1f-2a6d5e7c8b90', $version, time() - $age, ['analytics' => 1], ['gtag' => 1], [], true)->getValue());
            $helpers = $this->extension($request);

            return [$helpers->hasConsent('gtag'), $helpers->hasPurposeConsent('analytics')];
        };

        self::assertSame([true, true], $read(60, 2), 'valid choice');
        self::assertSame([false, false], $read($front->config['repromptDays'] * 86400 + 60, 2), 'expired choice');
        self::assertSame([false, false], $read(60, 1), 'choice older than the last substantial version');
    }

    /** R1-10: the contract version is read in the templates of a theme, not only in those of the module. */
    public function testTheContractVersionIsAGlobalOfEveryTemplate(): void
    {
        $twig = $this->twig('flexy');
        $twig->addExtension($this->extension(Request::create('/')));

        self::assertSame(TemplateContext::CONTRACT, $twig->createTemplate('{{ ccm_contract }}')->render([]));
        self::assertSame('theme', $twig->createTemplate('{{ ccm_contract }}')->render(['ccm_contract' => 'theme']), 'a variable of the context still wins');
    }

    public function testThePlaceholderAndTheOpenLinkAreRenderedFromThePublishedConfiguration(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $this->publisher->publish('alice');
        $helpers = $this->extension(Request::create('/'));

        $html = $helpers->placeholder('youtube', ['title' => 'Vidéo <produit>', 'src' => 'https://www.youtube.com/embed/x?a=1&b=2', 'bad name' => 'x']);
        self::assertStringContainsString('data-ccm-placeholder="youtube"', $html);
        self::assertStringContainsString('data-ccm-src="https://www.youtube.com/embed/x?a=1&amp;b=2"', $html);
        self::assertStringContainsString('data-ccm-attr-title="Vidéo &lt;produit&gt;"', $html);
        self::assertStringNotContainsString('bad name', $html);
        self::assertSame('', $helpers->placeholder('not-published'));

        self::assertMatchesRegularExpression('~<a [^>]*data-ccm-open[^>]*>[^<]+</a>~', $helpers->openLink());
        self::assertStringContainsString('>Voir<', $helpers->openLink('Voir'));
    }

    public function testTheListenerRewritesOnlyHtmlOfTheFrontAndOnlyWithTheOption(): void
    {
        $draft = $this->publishableDraft();
        $this->ccm->drafts->replace($draft, 'alice');
        $this->publisher->publish('alice');
        $page = '<html><body><iframe src="https://www.youtube.com/embed/abc" title="v"></iframe></body></html>';

        $run = function (Request $request, Response $response): string {
            $context = new TemplateContext(new FrontTexts(new CatalogueTranslator()));
            $listener = new IframeRewriteListener($this->configs, new IframeRewriter(), new PlaceholderRenderer($context, $this->twig('flexy')));
            $listener->onResponse(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

            return (string) $response->getContent();
        };

        self::assertStringContainsString('data-ccm-placeholder="youtube"', $run(Request::create('/produit'), new Response($page)), 'an unprepared response has no Content-Type yet');
        self::assertSame($page, $run(Request::create('/produit'), new Response($page, 200, ['Content-Type' => 'application/json'])));
        self::assertSame($page, $run(Request::create('/admin/products'), new Response($page)));
        self::assertSame($page, $run(Request::create('/produit'), new Response($page, 404)));

        $draft['general']['block_embedded_iframes'] = false;
        $this->ccm->drafts->replace($draft, 'alice');
        $this->publisher->publish('alice');
        $this->configs = new FrontConfigProvider($this->ccm->versions, $this->ccm->builder, $this->ccm->assembler, $this->ccm->drafts, $this->tokens, $this->hashes, $this->security(true), $this->ccm->pointer, $this->ccm->pool);
        self::assertSame($page, $run(Request::create('/produit'), new Response($page)));
    }

    /** SPEC annex E: the attributes the engine reads, in both bundled themes. */
    public function testTheTemplatesOfBothThemesKeepTheAttributesTheEngineNeeds(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $this->publisher->publish('alice');
        $front = $this->configs->forRequest(Request::create('/'));
        $variables = (new TemplateContext(new FrontTexts(new CatalogueTranslator())))->build($front);

        foreach (['default', 'flexy'] as $theme) {
            $twig = $this->twig($theme);
            $banner = $twig->render('CookieConsentManager/banner.html.twig', $variables);
            $panel = $twig->render('CookieConsentManager/preferences-panel.html.twig', $variables);
            $placeholder = $twig->render('CookieConsentManager/placeholder.html.twig', $variables + ['service' => ['key' => 'youtube', 'name' => 'YouTube', 'purpose_label' => 'Media', 'policy_url' => ''], 'attributes' => ['title' => 'x'], 'src' => 'https://x.test']);
            $box = $twig->render('CookieConsentManager/placeholder.html.twig', $variables + ['template_mode' => true, 'service' => ['key' => '{{key}}', 'name' => '{{name}}', 'purpose_label' => '', 'policy_url' => '']]);

            foreach (['data-ccm-banner', ' hidden', 'data-ccm-action="reject_all"', 'data-ccm-action="accept_all"', 'data-ccm-action="open"', 'role="dialog"', 'id="ccm-banner-body" role="region" aria-labelledby="ccm-banner-title" data-ccm-scroll'] as $needle) {
                self::assertStringContainsString($needle, $banner, $theme.' banner '.$needle);
            }
            self::assertStringNotContainsString('close_reject', $banner, 'the close cross is off by default');

            foreach (['data-ccm-panel', ' hidden', 'role="switch"', 'data-ccm-purpose="analytics"', 'data-ccm-service="gtag"', 'data-ccm-action="save"', 'data-ccm-consent-id'] as $needle) {
                self::assertStringContainsString($needle, $panel, $theme.' panel '.$needle);
            }
            self::assertStringContainsString('aria-checked="false"', $panel, 'every switch starts off');

            foreach (['data-ccm-placeholder="youtube"', 'data-ccm-src="https://x.test"', 'data-ccm-attr-title="x"', 'data-ccm-action="service_allow"', 'data-ccm-service="youtube"', 'data-ccm-open'] as $needle) {
                self::assertStringContainsString($needle, $placeholder, $theme.' placeholder '.$needle);
            }
            self::assertStringNotContainsString('tabindex', $banner, $theme.': the text is a tab stop only when it scrolls (the engine decides)');
            $footer = $twig->render('CookieConsentManager/open-link.html.twig', $variables + ['label' => 'Manage', 'wrapper' => true]);
            self::assertMatchesRegularExpression('~^<nav class="[^"]*ccm-footer-link" aria-label="Manage"><a [^>]*data-ccm-open>Manage</a></nav>$~', trim($footer), $theme.': the footer link sits in a named landmark');
            self::assertStringContainsString('data-ccm-service="{{key}}"', $box);
            self::assertStringContainsString('{{name}}', $box);

            self::assertSame($this->classOf($banner, 'reject_all'), $this->classOf($banner, 'accept_all'), $theme.': Accept and Reject share one class');
            self::assertSame($this->classOf($panel, 'reject_all'), $this->classOf($panel, 'accept_all'));
        }
    }

    private function classOf(string $html, string $action): string
    {
        preg_match('~<button[^>]*data-ccm-action="'.$action.'"[^>]*>~', $html, $button);
        preg_match('~class="([^"]+)"~', $button[0] ?? '', $class);

        return $class[1] ?? '';
    }

    public function testTheCloseCrossAppearsWhenTheOptionIsOn(): void
    {
        $draft = $this->publishableDraft();
        $draft['general']['close_button'] = true;
        $draft['general']['layout'] = 'modal';
        $this->ccm->drafts->replace($draft, 'alice');
        $this->publisher->publish('alice');

        $variables = (new TemplateContext(new FrontTexts(new CatalogueTranslator())))->build($this->configs->forRequest(Request::create('/')));
        $banner = $this->twig('flexy')->render('CookieConsentManager/banner.html.twig', $variables);

        self::assertStringContainsString('data-ccm-action="close_reject"', $banner);
        self::assertStringContainsString($variables['texts']['close'], $banner);
        self::assertStringContainsString('aria-modal="true"', $banner);
    }

    public function testThePageWithoutJavaScriptExplainsTheChoicesAndListsTheServices(): void
    {
        $request = Request::create('/ccm/preferences');
        $stack = new RequestStack();
        $stack->push($request);
        $twig = $this->twig('flexy');
        $make = fn (): PreferencesPageController => new PreferencesPageController(
            $this->configs,
            new TemplateContext(new FrontTexts(new CatalogueTranslator())),
            new ConsentThemeHook($stack, $this->configs, new TemplateContext(new FrontTexts(new CatalogueTranslator())), new FrontTexts(new CatalogueTranslator()), new StyleSheet($this->module.'/assets/css/ccm.css'), $twig, $this->module.'/assets/js/head.js'),
            $twig,
            new class extends FrontThemeActivator {
                public function __construct()
                {
                }

                public function activate(): void
                {
                }
            },
        );

        try {
            $make()($request);
            self::fail('404 expected while nothing is published');
        } catch (NotFoundHttpException) {
            self::addToAssertionCount(1);
        }

        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $this->publisher->publish('alice');
        $this->configs = new FrontConfigProvider($this->ccm->versions, $this->ccm->builder, $this->ccm->assembler, $this->ccm->drafts, $this->tokens, $this->hashes, $this->security(true), $this->ccm->pointer, $this->ccm->pool);
        $response = $make()($request);
        $html = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Google Analytics 4', $html);
        self::assertStringContainsString('YouTube', $html);
        self::assertStringContainsString('<noscript>', $html);
        self::assertStringContainsString('data-ccm-open', $html);
        self::assertStringContainsString('id="ccm-config"', $html, 'visitors with JavaScript get the panel');
        self::assertStringContainsString('noindex', $html);
    }

    /** The hash `ccm:config:publish` announces is the one the page serves, with nothing changed since. */
    public function testThePublishedHashIsTheServedHash(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $version = $this->ccm->publisher->publish('alice');

        self::assertSame($version->getServedHash(), $this->hashes->forVersion($version));
        $this->controller()->script($version->getVersion(), $version->getServedHash());
    }
}
