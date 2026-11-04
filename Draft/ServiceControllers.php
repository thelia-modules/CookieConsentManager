<?php

declare(strict_types=1);

namespace CookieConsentManager\Draft;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Legal entity responsible for each verified catalogue service, used while the administrator has not typed one. */
final class ServiceControllers
{
    /** @var array<string, string>|null */
    private ?array $entities = null;

    public function __construct(
        #[Autowire(value: __DIR__.'/../Resources/catalogue/controllers.json')]
        private readonly string $path,
    ) {
    }

    public function default(string $key): string
    {
        if (null === $this->entities) {
            $content = is_file($this->path) ? file_get_contents($this->path) : false;
            $this->entities = false === $content ? [] : array_map('strval', (array) json_decode($content, true, 512, \JSON_THROW_ON_ERROR));
        }

        return $this->entities[$key] ?? '';
    }

    /** @param array<string, mixed> $service */
    public function effective(array $service): string
    {
        $typed = trim((string) ($service['controller'] ?? ''));

        return '' !== $typed ? $typed : $this->default((string) $service['key']);
    }
}
