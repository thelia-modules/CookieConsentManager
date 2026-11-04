<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Front\ServedScriptAssembler;
use CookieConsentManager\Publication\PublishedConfigBuilder;
use CookieConsentManager\Publication\SnapshotNormalizer;
use PHPUnit\Framework\TestCase;

final class SnapshotNormalizerTest extends TestCase
{
    /** @return array<string, mixed> a snapshot published by the 1.0.0 release (real, sanitized) */
    private function release10(): array
    {
        return (array) json_decode((string) file_get_contents(__DIR__.'/../Fixtures/snapshot-1.0.json'), true, 512, \JSON_THROW_ON_ERROR);
    }

    public function testASnapshotOfRelease10IsRenderedAsItWasPublished(): void
    {
        $raw = $this->release10();
        $normalized = (new SnapshotNormalizer())->normalize($raw);

        self::assertSame('ccm-snapshot/1', $raw['schema']);
        // what 1.0.0 did not write yet gets its default, nothing else moves
        self::assertSame([], $normalized['config']['policy']['urls']);
        unset($normalized['config']['policy']['urls']);
        self::assertSame($raw, $normalized);

        $builder = new PublishedConfigBuilder();
        self::assertEquals($builder->build($raw, 'fr_FR', 8, 'be74bc261fa4', 0), $builder->build((new SnapshotNormalizer())->normalize($raw), 'fr_FR', 8, 'be74bc261fa4', 0));
        $assembler = new ServedScriptAssembler(\dirname(__DIR__, 3));
        self::assertSame($assembler->assemble($raw), $assembler->assemble((new SnapshotNormalizer())->normalize($raw)), 'the served script, hence its hash, is the same');
    }

    public function testAFieldAddedAfterTheSnapshotWasPublishedGetsItsDefault(): void
    {
        $snapshot = $this->release10();
        unset($snapshot['config']['general']['floating_button'], $snapshot['config']['consent_mode']['uet'], $snapshot['config']['appearance']['colors']['text']);
        $snapshot['config']['general']['layout'] = 'modal';

        $normalized = (new SnapshotNormalizer())->normalize($snapshot);

        self::assertFalse($normalized['config']['general']['floating_button']);
        self::assertSame(['enabled' => false], $normalized['config']['consent_mode']['uet']);
        self::assertSame('#111827', $normalized['config']['appearance']['colors']['text']);
        self::assertSame('modal', $normalized['config']['general']['layout'], 'a value of the snapshot is never replaced');

        $config = (new PublishedConfigBuilder())->build($normalized, 'fr_FR', 8, 'be74bc261fa4', 0);
        self::assertFalse($config['options']['floatingButton']);
        self::assertFalse($config['gcm']['uet']);
    }

    public function testASnapshotWithoutSchemaIsTakenForTheFirstOneAndAnUnknownSchemaIsRefused(): void
    {
        $snapshot = $this->release10();
        unset($snapshot['schema']);
        self::assertSame('bar', (new SnapshotNormalizer())->normalize($snapshot)['config']['general']['layout']);

        $snapshot['schema'] = 'ccm-snapshot/2';
        $this->expectException(\UnexpectedValueException::class);
        (new SnapshotNormalizer())->normalize($snapshot);
    }
}
