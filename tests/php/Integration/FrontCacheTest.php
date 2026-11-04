<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Front\AssembledScriptHasher;
use CookieConsentManager\Front\FooterLinkResolver;
use CookieConsentManager\Front\FrontConfigProvider;
use CookieConsentManager\Front\FrontTexts;
use CookieConsentManager\Front\PreviewToken;
use CookieConsentManager\Front\ServedHashResolver;
use CookieConsentManager\Front\ServedScriptAssembler;
use CookieConsentManager\Front\TemplateContext;
use CookieConsentManager\Publication\ModuleVersion;
use CookieConsentManager\Publication\Publisher;
use CookieConsentManager\Publication\SnapshotAssembler;
use CookieConsentManager\Tests\Support\CatalogueTranslator;
use CookieConsentManager\Tests\Support\CcmTestCase;
use CookieConsentManager\Tests\Support\CountingScriptHasher;
use CookieConsentManager\Tests\Support\MutableOverrides;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;

/** What a page view costs once the cache is warm: no query of the module, no script file read, no template hashed. */
final class FrontCacheTest extends CcmTestCase
{
    private CountingScriptHasher $hasher;
    private MutableOverrides $templates;
    private Publisher $publisher;

    protected function setUp(): void
    {
        parent::setUp();

        $module = \dirname(__DIR__, 3);
        $this->hasher = new CountingScriptHasher(new AssembledScriptHasher(new ServedScriptAssembler($module)));
        $this->templates = new MutableOverrides();
        $this->publisher = new Publisher($this->ccm->drafts, $this->ccm->validator, new SnapshotAssembler($this->ccm->catalogue, new ModuleVersion($module.'/Config/module.xml'), $this->templates), $this->hasher, $this->ccm->versions, $this->ccm->cache, new ModuleVersion($module.'/Config/module.xml'), $this->ccm->pointer);
    }

    /** A new request of a worker, or of PHP-FPM: new services, the application cache is the only thing shared. */
    private function newRequest(): FrontConfigProvider
    {
        $module = \dirname(__DIR__, 3);
        $hashes = new ServedHashResolver($this->ccm->versions, $this->hasher, new ModuleVersion($module.'/Config/module.xml'), $this->templates, $this->ccm->pool);

        return new FrontConfigProvider($this->ccm->versions, $this->ccm->builder, $this->ccm->assembler, $this->ccm->drafts, new PreviewToken('test-secret'), $hashes, $this->createStub(SecurityContext::class), $this->ccm->pointer, $this->ccm->pool, $this->ccm->urls);
    }

    private function queries(callable $work): int
    {
        $connection = Propel::getConnection('TheliaMain');
        self::assertInstanceOf(ConnectionWrapper::class, $connection);
        $connection->useDebug(true);
        $work();
        $count = $connection->getQueryCount();
        $connection->useDebug(false);

        return $count;
    }

    private function publish(bool $substantial = false): int
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');

        return $this->publisher->publish('alice', $substantial)->getVersion();
    }

    public function testAPageViewIssuesNoQueryOnceTheCacheIsWarm(): void
    {
        $number = $this->publish();
        $warm = $this->newRequest()->forRequest(Request::create('/'));
        self::assertSame($number, $warm->config['version']);

        $this->hasher->calls = 0;
        $this->templates->lists = 0;
        $served = null;
        $queries = $this->queries(function () use (&$served): void {
            for ($view = 0; $view < 3; ++$view) {
                $served = $this->newRequest()->forRequest(Request::create('/'));
            }
        });

        self::assertSame(0, $queries, 'no SQL query of the module on a warm page view');
        self::assertSame(0, $this->hasher->calls, 'the script is not read and hashed again');
        self::assertSame(0, $this->templates->lists, 'the overrides are not listed and hashed again');
        self::assertEquals($warm, $served);
    }

    public function testAPublicationIsSeenByTheNextRequestWithoutWaitingForTheCacheToExpire(): void
    {
        $first = $this->publish();
        self::assertSame($first, $this->newRequest()->forRequest(Request::create('/'))->config['version']);

        $second = $this->publish(true);

        $config = $this->newRequest()->forRequest(Request::create('/'))->config;
        self::assertSame($second, $config['version']);
        self::assertSame($second, $config['substantialVersion']);
    }

    public function testAnUpdateOfTheThemeOverridesChangesTheServedHashAtTheNextRequest(): void
    {
        $this->publish();
        $before = $this->newRequest()->forRequest(Request::create('/'))->config;

        $this->templates->overridden = true;
        $after = $this->newRequest()->forRequest(Request::create('/'))->config;

        self::assertNotSame($before['hash'], $after['hash']);
        self::assertSame($before['version'], $after['version']);
        self::assertStringContainsString($after['hash'], $after['script']);
    }

    public function testAnotherLanguageIsNotServedFromTheCacheOfThisOne(): void
    {
        $this->publish();
        $configs = $this->newRequest();

        self::assertSame('fr_FR', $configs->forLocale('fr_FR')->config['locale']);
        self::assertSame('en_US', $this->newRequest()->forLocale('en_US')->config['locale']);
        self::assertSame('fr_FR', $this->newRequest()->forLocale('fr_FR')->config['locale']);
    }

    public function testNothingPublishedIsAnsweredWithoutAQueryOnceKnown(): void
    {
        self::assertNull($this->newRequest()->forRequest(Request::create('/')));

        self::assertSame(0, $this->queries(fn () => $this->newRequest()->forRequest(Request::create('/'))));
    }

    public function testTheFooterResolverTestsTheSlotBeforeAnyQuery(): void
    {
        if (!interface_exists('Thelia\Core\Content\Slot\ContentSlotResolverInterface')) {
            self::markTestSkipped('The slot footer_links exists from Thelia 3.2.');
        }

        $this->publish();
        $resolver = new FooterLinkResolver($this->newRequest(), new TemplateContext(new FrontTexts(new CatalogueTranslator())), []);

        self::assertSame(0, $this->queries(static fn () => self::assertNull($resolver->resolve('another_slot', 'fr_FR'))));

        $links = $resolver->resolve('footer_links', 'fr_FR');
        self::assertNotNull($links);
        self::assertSame(0, $this->queries(static fn () => $resolver->resolve('footer_links', 'fr_FR')), 'the texts come from the cached configuration');
    }
}
