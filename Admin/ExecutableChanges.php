<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Catalogue\CatalogueReader;

/**
 * What a draft change puts on the storefront as a script: the code and the address of a custom service, and the value of a
 * parameter that the catalogue flags executable (the origin of a loaded script, inline code, markup). Only the right that
 * publishes may make such a change, on every screen that writes services.
 */
final readonly class ExecutableChanges
{
    public function __construct(private CatalogueReader $catalogue)
    {
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function between(array $before, array $after): bool
    {
        $known = array_column($before['services'] ?? [], null, 'key');
        foreach ($after['services'] ?? [] as $service) {
            $previous = $known[$service['key']] ?? [];
            foreach (['code', 'script_url'] as $field) {
                if ((string) ($service[$field] ?? '') !== (string) ($previous[$field] ?? '')) {
                    return true;
                }
            }
            foreach ((array) ($service['parameters'] ?? []) as $name => $value) {
                if ('' !== (string) $value && (string) $value !== (string) ($previous['parameters'][$name] ?? '') && $this->isExecutableParameter((string) $service['key'], (string) $name)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** A path of the draft (`services.<key>.parameters.<name>`, `services.<key>.code`...) that holds something the storefront runs. */
    public function isExecutablePath(string $path): bool
    {
        if (1 === preg_match('/^services\.([^.]+)\.(?:code|script_url)$/D', $path)) {
            return true;
        }

        return 1 === preg_match('/^services\.([^.]+)\.parameters\.(.+)$/D', $path, $match) && $this->isExecutableParameter($match[1], $match[2]);
    }

    private function isExecutableParameter(string $serviceKey, string $name): bool
    {
        return $this->catalogue->find($serviceKey)?->isExecutable($name) ?? true;
    }
}
