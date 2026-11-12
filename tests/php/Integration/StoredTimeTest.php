<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Admin\RegistryProof;
use CookieConsentManager\Front\AssembledScriptHasher;
use CookieConsentManager\Front\ServedHashResolver;
use CookieConsentManager\Front\ServedScriptAssembler;
use CookieConsentManager\Model\ConsentEntry;
use CookieConsentManager\Publication\ModuleVersion;
use CookieConsentManager\Tests\Support\CcmTestCase;
use CookieConsentManager\Tests\Support\NoOverriddenTemplates;

final class StoredTimeTest extends CcmTestCase
{
    private string $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zone = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->zone);
        parent::tearDown();
    }

    public function testAPublishedVersionIsDatedInUtcWhateverTheTimezoneOfTheShop(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $before = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $version = $this->ccm->publisher->publish('alice');

        $stored = new \DateTimeImmutable($this->ccm->versions->find($version->getVersion())->getCreatedAt()->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));
        self::assertLessThan(5, abs($stored->getTimestamp() - $before->getTimestamp()), 'the version date is the UTC time of the publication');
    }

    public function testTheProofSheetStatesUtcAndShowsTheLocalTimeNextToIt(): void
    {
        $this->ccm->drafts->replace($this->publishableDraft(), 'alice');
        $version = $this->ccm->publisher->publish('alice');
        $entry = (new ConsentEntry())
            ->setConsentId('11111111-2222-4333-8444-555555555555')
            ->setRecordedAt(new \DateTimeImmutable('2026-10-31 23:03:27', new \DateTimeZone('UTC')))
            ->setAction('accept_all')->setSource('banner')->setVersion($version->getVersion())->setVersionHash('abc')
            ->setLocale('fr_FR')->setPath('/')->setBrowser('Chrome 130')->setPurposesJson('{}')->setServicesJson('{}')->setExemptJson('{}');

        $module = \dirname(__DIR__, 3);
        $proof = (new RegistryProof($this->ccm->versions, new ServedHashResolver($this->ccm->versions, new AssembledScriptHasher(new ServedScriptAssembler($module)), new ModuleVersion($module.'/Config/module.xml'), new NoOverriddenTemplates(), $this->ccm->pool)))->build($entry);

        self::assertSame('2026-10-31 23:03:27 UTC', $proof['entry']['recorded_at']['utc']);
        self::assertSame('2026-11-01 08:03:27', $proof['entry']['recorded_at']['local']);
        self::assertSame('Asia/Tokyo', $proof['entry']['recorded_at']['zone']);
        self::assertStringEndsWith(' UTC', $proof['version']['created_at']['utc']);
    }
}
