<?php

declare(strict_types=1);

namespace CookieConsentManager\Publication;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ModuleVersion
{
    /** @var array{int, string}|null modification time of the descriptor, version read at that time */
    private ?array $read = null;

    public function __construct(
        #[Autowire(value: __DIR__.'/../Config/module.xml')]
        private readonly string $descriptorPath,
    ) {
    }

    // One stat per call: the descriptor is parsed again only after it changed (a long-lived worker sees an update of the module).
    public function current(): string
    {
        clearstatcache(true, $this->descriptorPath);
        $modified = (int) @filemtime($this->descriptorPath);
        if (null === $this->read || $this->read[0] !== $modified) {
            $descriptor = simplexml_load_file($this->descriptorPath);
            $this->read = [$modified, false === $descriptor ? 'unknown' : (string) $descriptor->version];
        }

        return $this->read[1];
    }
}
