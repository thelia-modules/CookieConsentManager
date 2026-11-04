<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Proposed\ProposedServiceSynchronizer;
use CookieConsentManager\Tests\Support\CcmTestCase;
use CookieConsentManager\Validation\DraftValidator;

final class ProposedServicesTest extends CcmTestCase
{
    public function testDeclaredServicesEnterTheDraftAsProposedAndInactive(): void
    {
        $report = $this->synchronizer()->synchronize();

        self::assertSame(['payzen_antifraud', 'payzen_tracking'], $report['added']);
        self::assertCount(3, $report['alerts'], 'the incomplete tag, the catalogue collision and the key declared twice are refused');

        $services = array_column($this->ccm->drafts->load()['services'], null, 'key');
        self::assertFalse($services['payzen_tracking']['enabled']);
        self::assertTrue($services['payzen_tracking']['proposed']);
        self::assertSame('script', $services['payzen_tracking']['kind']);
        self::assertSame('declarative', $services['payzen_antifraud']['kind']);
        self::assertTrue($services['payzen_antifraud']['strictly_necessary']);
        self::assertSame([['name' => 'payzen_fp', 'domain' => '.payzen.eu', 'duration_days' => 1]], $services['payzen_antifraud']['cookies']);
        self::assertArrayNotHasKey('youtube', array_filter($services, static fn (array $service): bool => isset($service['module'])));
    }

    public function testReplayingKeepsWhatTheAdminDidAndChangesNothingElse(): void
    {
        $this->synchronizer()->synchronize();

        $draft = $this->ccm->drafts->load();
        foreach ($draft['services'] as &$service) {
            if ('payzen_tracking' === $service['key']) {
                $service['enabled'] = true;
                $service['proposed'] = false;
            }
        }
        unset($service);
        $this->ccm->drafts->replace($draft, 'alice');

        $report = $this->synchronizer()->synchronize();

        self::assertSame([], $report['added']);
        self::assertSame([], $report['updated']);
        $tracking = array_column($this->ccm->drafts->load()['services'], null, 'key')['payzen_tracking'];
        self::assertTrue($tracking['enabled']);
        self::assertFalse($tracking['proposed']);
    }

    public function testAProposedServiceStaysOutOfAPublicationUntilAnAdminEnablesIt(): void
    {
        $this->synchronizer()->synchronize();
        $draft = $this->ccm->drafts->load();
        $draft['policy']['url'] = '/politique';
        $this->ccm->drafts->replace($draft, 'alice');

        $version = $this->ccm->publisher->publish('alice');

        $keys = array_column($this->ccm->versions->snapshot($version)['services_resolved'], 'key');
        self::assertSame([], $keys);
        self::assertFalse(DraftValidator::hasErrors($this->ccm->validator->validate($this->ccm->drafts->load())));
    }

    public function testAnEssentialDeclarationNeedsItsJustification(): void
    {
        $synchronizer = new ProposedServiceSynchronizer([
            ['service_id' => 'x.ccm', 'attributes' => ['key' => 'x_necessary', 'name' => 'X', 'controller' => 'X', 'purpose' => 'essential', 'policy_url' => 'https://x.test', 'strictly_necessary' => true]],
        ], $this->ccm->drafts, $this->ccm->catalogue);
        $synchronizer->synchronize();
        $draft = $this->ccm->drafts->load();
        $draft['services'][0]['enabled'] = true;

        $codes = array_map(static fn ($issue): string => $issue->code, $this->ccm->validator->validate($this->ccm->normalizer->normalize($draft)));

        self::assertContains('justification_missing', $codes);
    }

    private function synchronizer(): ProposedServiceSynchronizer
    {
        return new ProposedServiceSynchronizer([
            ['service_id' => 'payzen.ccm.antifraud', 'attributes' => [
                'key' => 'payzen_antifraud', 'name' => 'Payzen anti-fraude', 'controller' => 'Lyra Network',
                'purpose' => 'essential', 'strictly_necessary' => true, 'justification' => 'Sécurité du paiement.',
                'policy_url' => 'https://payzen.eu/privacy',
                'cookies' => [['name' => 'payzen_fp', 'domain' => '.payzen.eu', 'duration_days' => 1]],
            ]],
            ['service_id' => 'payzen.ccm.tracking', 'attributes' => [
                'key' => 'payzen_tracking', 'name' => 'Payzen suivi', 'controller' => 'Lyra Network',
                'purpose' => 'analytics', 'policy_url' => 'https://payzen.eu/privacy', 'script' => 'https://static.payzen.eu/track.js',
            ]],
            ['service_id' => 'bad.ccm', 'attributes' => ['key' => 'incomplete']],
            ['service_id' => 'clash.ccm', 'attributes' => ['key' => 'youtube', 'name' => 'Y', 'controller' => 'Y', 'purpose' => 'media', 'policy_url' => 'https://y.test']],
            ['service_id' => 'clash2.ccm', 'attributes' => ['key' => 'payzen_tracking', 'name' => 'Y', 'controller' => 'Y', 'purpose' => 'media', 'policy_url' => 'https://y.test', 'script' => 'https://y.test/a.js']],
        ], $this->ccm->drafts, $this->ccm->catalogue);
    }
}
