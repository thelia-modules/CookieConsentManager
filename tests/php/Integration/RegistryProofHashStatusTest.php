<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Admin\RegistryProof;
use CookieConsentManager\Front\AssembledScriptHasher;
use CookieConsentManager\Front\ServedHashResolver;
use CookieConsentManager\Front\ServedScriptAssembler;
use CookieConsentManager\Model\ConsentEntry;
use CookieConsentManager\Model\Version;
use CookieConsentManager\Publication\ModuleVersion;
use CookieConsentManager\Tests\Support\CcmTestCase;
use CookieConsentManager\Tests\Support\NoOverriddenTemplates;

final class RegistryProofHashStatusTest extends CcmTestCase
{
    private string $module;
    private string $updatedDescriptor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = \dirname(__DIR__, 3);
        $this->updatedDescriptor = tempnam(sys_get_temp_dir(), 'ccm-module-').'.xml';
        file_put_contents($this->updatedDescriptor, '<module><version>99.0.0</version></module>');
    }

    protected function tearDown(): void
    {
        @unlink($this->updatedDescriptor);
        parent::tearDown();
    }

    public function testTheHashStoredAtPublicationIsReportedAsPublished(): void
    {
        $version = $this->publishedVersion();

        self::assertSame('published', $this->statusOf($version, $version->getServedHash(), $this->module.'/Config/module.xml'));
    }

    public function testTheHashServedNowAfterAModuleUpdateIsReportedAsCurrent(): void
    {
        $version = $this->publishedVersion();
        $servedNow = $this->resolver($this->updatedDescriptor)->forVersion($version);
        self::assertNotSame($version->getServedHash(), $servedNow, 'the module version enters the served hash');

        self::assertSame('current', $this->statusOf($version, $servedNow, $this->updatedDescriptor));
    }

    public function testAHashThatMatchesNoKnownRenderingIsReportedAsUnknown(): void
    {
        $version = $this->publishedVersion();

        self::assertSame('unknown', $this->statusOf($version, 'aaaaaaaaaaaa', $this->updatedDescriptor));
        self::assertSame('unknown', $this->statusOf($version, 'aaaaaaaaaaaa', $this->module.'/Config/module.xml'));
    }

    public function testAnEntryWhoseVersionNoLongerExistsHasNoHashStatus(): void
    {
        $entry = $this->entry(9999, 'aaaaaaaaaaaa');

        $proof = (new RegistryProof($this->ccm->versions, $this->resolver($this->module.'/Config/module.xml')))->build($entry);

        self::assertNull($proof['hash_status']);
    }

    private function publishedVersion(): Version
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');

        return $this->ccm->publisher->publish('alice');
    }

    private function statusOf(Version $version, string $entryHash, string $descriptor): ?string
    {
        $proof = (new RegistryProof($this->ccm->versions, $this->resolver($descriptor)))->build($this->entry($version->getVersion(), $entryHash));

        return $proof['hash_status'];
    }

    private function resolver(string $descriptor): ServedHashResolver
    {
        return new ServedHashResolver($this->ccm->versions, new AssembledScriptHasher(new ServedScriptAssembler($this->module)), new ModuleVersion($descriptor), new NoOverriddenTemplates(), $this->ccm->pool);
    }

    private function entry(int $version, string $hash): ConsentEntry
    {
        return (new ConsentEntry())
            ->setConsentId('11111111-2222-4333-8444-555555555555')
            ->setRecordedAt(new \DateTimeImmutable('2026-11-05 10:00:00', new \DateTimeZone('UTC')))
            ->setAction('accept_all')->setSource('banner')->setVersion($version)->setVersionHash($hash)
            ->setLocale('fr_FR')->setPath('/')->setBrowser('Chrome 130')->setPurposesJson('{}')->setServicesJson('{}')->setExemptJson('{}');
    }
}
