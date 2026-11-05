<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Admin\AdminGuard;
use CookieConsentManager\Admin\AdminTwigExtension;
use CookieConsentManager\Admin\FieldLabeler;
use CookieConsentManager\Front\AssembledScriptHasher;
use CookieConsentManager\Front\FrontConfigProvider;
use CookieConsentManager\Front\PreviewToken;
use CookieConsentManager\Front\ServedHashResolver;
use CookieConsentManager\Front\ServedScriptAssembler;
use CookieConsentManager\Publication\ModuleVersion;
use CookieConsentManager\Publication\Publisher;
use CookieConsentManager\Publication\SnapshotAssembler;
use CookieConsentManager\Tests\Support\CatalogueTranslator;
use CookieConsentManager\Tests\Support\CcmTestCase;
use CookieConsentManager\Tests\Support\NoOverriddenTemplates;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Tools\TokenProvider;

/** A worker (FrontController of FrankenPHP, RoadRunner) keeps services alive between requests: the memo of a service is cleared by `reset()`, called by the kernel after each request. */
final class WorkerModeTest extends CcmTestCase
{
    private FrontConfigProvider $configs;
    private ServedHashResolver $hashes;
    private Publisher $publisher;

    protected function setUp(): void
    {
        parent::setUp();

        $module = \dirname(__DIR__, 3);
        $moduleVersion = new ModuleVersion($module.'/Config/module.xml');
        $hasher = new AssembledScriptHasher(new ServedScriptAssembler($module));
        $templates = new NoOverriddenTemplates();
        $this->hashes = new ServedHashResolver($this->ccm->versions, $hasher, $moduleVersion, $templates, $this->ccm->pool);
        $this->publisher = new Publisher($this->ccm->drafts, $this->ccm->validator, new SnapshotAssembler($this->ccm->catalogue, $moduleVersion, $templates), $hasher, $this->ccm->versions, $this->ccm->cache, $moduleVersion, $this->ccm->pointer);
        $security = $this->createStub(SecurityContext::class);
        $this->configs = new FrontConfigProvider($this->ccm->versions, $this->ccm->builder, $this->ccm->assembler, $this->ccm->drafts, new PreviewToken(str_repeat('t', 32)), $this->hashes, $security, $this->ccm->pointer, $this->ccm->pool, $this->ccm->urls);
    }

    public function testEveryServiceThatMemoisesAcrossRequestsIsResetByTheKernel(): void
    {
        $adminExtension = new AdminTwigExtension(new FieldLabeler($this->ccm->catalogue, new CatalogueTranslator()), $this->ccm->drafts, new RequestStack(), new CatalogueTranslator(), new AdminGuard($this->createStub(TokenProvider::class), $this->createStub(SecurityContext::class)));

        foreach ([$this->configs, $this->hashes, $adminExtension] as $service) {
            self::assertInstanceOf(ResetInterface::class, $service, $service::class.' holds state between requests');
        }
    }

    public function testAPublicationIsServedByTheSameInstanceAfterTheReset(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $first = $this->publisher->publish('alice');
        $before = $this->configs->forRequest(Request::create('/'))->config;
        self::assertSame($first->getVersion(), $before['version']);

        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $second = $this->publisher->publish('alice', true);
        $this->configs->reset();
        $this->hashes->reset();

        $after = $this->configs->forRequest(Request::create('/'))->config;
        self::assertSame($second->getVersion(), $after['version']);
        self::assertSame($second->getVersion(), $after['substantialVersion']);
        self::assertStringContainsString('/'.$second->getVersion().'-', $after['script']);
    }

    public function testTheBackOfficeDraftIsReadAgainAfterTheReset(): void
    {
        $requests = new RequestStack();
        $requests->push(Request::create('/'));
        $extension = new AdminTwigExtension(new FieldLabeler($this->ccm->catalogue, new CatalogueTranslator()), $this->ccm->drafts, $requests, new CatalogueTranslator(), new AdminGuard($this->createStub(TokenProvider::class), $this->createStub(SecurityContext::class)));

        $draft = $this->ccm->drafts->load();
        $draft['purposes'][0]['label']['en'] = 'First label';
        $this->ccm->drafts->replace($draft, 'alice');
        self::assertSame('First label', $extension->purpose($draft['purposes'][0]['code']));

        $draft['purposes'][0]['label']['en'] = 'Second label';
        $this->ccm->drafts->replace($draft, 'alice');
        $extension->reset();
        self::assertSame('Second label', $extension->purpose($draft['purposes'][0]['code']));
    }
}
