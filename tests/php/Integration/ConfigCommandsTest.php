<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Integration;

use CookieConsentManager\Command\ConfigImportCommand;
use CookieConsentManager\Command\ConfigPublishCommand;
use CookieConsentManager\Tests\Support\CcmTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ConfigCommandsTest extends CcmTestCase
{
    public function testImportReportsTheFieldsToConfirmAsWarningsAndExitsZero(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ccm');
        file_put_contents($file, (string) file_get_contents(\dirname(__DIR__).'/Fixtures/complete-config.json'));
        $tester = new CommandTester(new ConfigImportCommand($this->ccm->importer));

        $code = $tester->execute(['file' => $file]);
        unlink($file);

        $output = $tester->getDisplay();
        self::assertSame(0, $code);
        self::assertStringContainsString('Draft created from the file', $output);
        self::assertStringContainsString('[warning] policy.url', $output);
        self::assertStringNotContainsString('[error]', $output, 'a field waiting for confirmation is not an error');
    }

    public function testImportOfAnUnreadableFileFails(): void
    {
        $tester = new CommandTester(new ConfigImportCommand($this->ccm->importer));

        self::assertNotSame(0, $tester->execute(['file' => '/nonexistent/ccm.json']));
    }

    public function testPublishRefusesAnUnchangedConfigurationUnlessForced(): void
    {
        $this->ccm->importer->import((string) file_get_contents(\dirname(__DIR__).'/Fixtures/complete-config.json'), 'alice');
        $draft = $this->ccm->drafts->load();
        unset($draft['_meta']['environment_pending']);
        $this->ccm->drafts->replace($draft, 'alice');
        $tester = new CommandTester(new ConfigPublishCommand($this->ccm->publisher, $this->ccm->drafts));

        self::assertSame(0, $tester->execute([]));
        $first = $this->ccm->versions->latest()->getVersion();

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('Nothing to publish', $tester->getDisplay());
        self::assertStringContainsString('--force', $tester->getDisplay());
        self::assertSame($first, $this->ccm->versions->latest()->getVersion());

        self::assertSame(0, $tester->execute(['--force' => true]));
        self::assertSame($first + 1, $this->ccm->versions->latest()->getVersion());
    }
}
