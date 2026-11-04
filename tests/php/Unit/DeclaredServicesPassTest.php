<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Unit;

use CookieConsentManager\Compiler\DeclaredServicesPass;
use CookieConsentManager\CookieConsentManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class DeclaredServicesPassTest extends TestCase
{
    public function testCollectsTheTagsWithTheirNestedAttributesIntoAParameter(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('payzen.ccm.tracking', (new Definition(\stdClass::class))->addTag('ccm.service', [
            'key' => 'payzen_tracking',
            'purpose' => 'analytics',
            'cookies' => [['name' => 'payzen_conv', 'domain' => '.payzen.eu', 'duration_days' => 90]],
        ]));
        $container->setDefinition('other', new Definition(\stdClass::class));

        (new DeclaredServicesPass())->process($container);

        $declared = $container->getParameter(CookieConsentManager::PARAMETER_DECLARED_SERVICES);
        self::assertCount(1, $declared);
        self::assertSame('payzen.ccm.tracking', $declared[0]['service_id']);
        self::assertSame('.payzen.eu', $declared[0]['attributes']['cookies'][0]['domain']);
    }

    public function testTheParameterExistsEvenWhenNoModuleDeclaresAnything(): void
    {
        $container = new ContainerBuilder();

        (new DeclaredServicesPass())->process($container);

        self::assertSame([], $container->getParameter(CookieConsentManager::PARAMETER_DECLARED_SERVICES));
    }
}
