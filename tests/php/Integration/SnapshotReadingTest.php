<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Model\Version;
use CookieConsentManager\Tests\Support\CcmTestCase;

final class SnapshotReadingTest extends CcmTestCase
{
    public function testAStoredSnapshotIsReadWithTheDefaultsOfTheFieldsItPredates(): void
    {
        $snapshot = (array) json_decode((string) file_get_contents(__DIR__.'/../Fixtures/snapshot-1.0.json'), true, 512, \JSON_THROW_ON_ERROR);
        // a field a later release adds to the configuration, absent from what was stored
        unset($snapshot['config']['general']['close_button'], $snapshot['config']['policy']['urls']);

        $version = (new Version())
            ->setVersion($this->ccm->versions->nextNumber())
            ->setSnapshot(json_encode($snapshot, \JSON_THROW_ON_ERROR))
            ->setConfigHash(str_repeat('a', 64))->setJsHash(str_repeat('b', 64))->setServedHash('0123456789ab')
            ->setModuleVersion('1.0.0')->setCreatedAt(new \DateTimeImmutable('2026-10-31 23:58:24', new \DateTimeZone('UTC')));
        $version->save();

        $read = $this->ccm->versions->snapshot($version);

        self::assertFalse($read['config']['general']['close_button']);
        self::assertSame([], $read['config']['policy']['urls']);
        self::assertSame('ccm-snapshot/1', $read['schema']);
        $config = $this->ccm->builder->build($read, 'en_US', $version->getVersion(), '0123456789ab', 0);
        self::assertFalse($config['options']['closeButton']);
        self::assertSame(['matomo'], array_column($config['exempt'], 'key'));
    }
}
