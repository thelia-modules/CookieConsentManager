<?php

declare(strict_types=1);

namespace CookieConsentManager\Compiler;

use CookieConsentManager\CookieConsentManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class DeclaredServicesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $declared = [];

        foreach ($container->findTaggedServiceIds(CookieConsentManager::TAG_SERVICE) as $serviceId => $tags) {
            foreach ($tags as $attributes) {
                $declared[] = ['service_id' => $serviceId, 'attributes' => $attributes];
            }
        }

        $container->setParameter(CookieConsentManager::PARAMETER_DECLARED_SERVICES, $declared);
    }
}
