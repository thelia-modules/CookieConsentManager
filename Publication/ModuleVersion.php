<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ModuleVersion
{
    public function __construct(
        #[Autowire(value: __DIR__.'/../Config/module.xml')]
        private string $descriptorPath,
    ) {
    }

    public function current(): string
    {
        $descriptor = simplexml_load_file($this->descriptorPath);

        return false === $descriptor ? 'unknown' : (string) $descriptor->version;
    }
}
