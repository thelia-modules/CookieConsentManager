<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Admin\Alert;
use CookieConsentManager\Admin\AlertCollector;
use CookieConsentManager\Validation\DraftValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/** A third-party module adds alerts and validation rules by implementing an interface: no change to the module is needed. */
final class ExtensionPointsTest extends TestCase
{
    private static function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__.'/../Fixtures')))->load('extension-services.php');
        $container->compile();

        return $container;
    }

    public function testAThirdPartyAlertProviderIsPickedUpByTheCollectorInPriorityOrder(): void
    {
        $alerts = self::container()->get(AlertCollector::class)->collect([]);

        self::assertSame(['early_info', 'third_party_info'], array_map(static fn (Alert $alert): string => $alert->code, $alerts));
    }

    public function testAThirdPartySectionValidatorIsPickedUpByTheDraftValidator(): void
    {
        $issues = self::container()->get(DraftValidator::class)->validate([]);

        self::assertSame(['third_party_rule'], array_map(static fn ($issue): string => $issue->code, $issues));
        self::assertTrue(DraftValidator::hasErrors($issues));
    }
}
