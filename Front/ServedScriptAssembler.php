<?php

declare(strict_types=1);

namespace CookieConsentManager\Front;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Concatenates the served script in the order docs/runtime-contracts.md ("Served script") sets: compatibility layer,
 * engine, boot, fragments of the active services (verbatim) and code of the custom ones, start.
 */
final readonly class ServedScriptAssembler
{
    public function __construct(
        #[Autowire(value: __DIR__.'/..')]
        private string $moduleDirectory,
    ) {
    }

    /** @param array<string, mixed> $snapshot */
    public function assemble(array $snapshot): string
    {
        $parts = [
            $this->read('assets/js/compat.js'),
            $this->read('assets/js/engine.js'),
            'CookieConsentManager.__boot();',
        ];

        foreach ($snapshot['services_resolved'] as $service) {
            $part = $this->service($service);
            if (null !== $part) {
                $parts[] = $part;
            }
        }

        $parts[] = 'CookieConsentManager.__start();';

        return implode("\n", $parts)."\n";
    }

    /** @param array<string, mixed> $service */
    private function service(array $service): ?string
    {
        if ('declarative' === $service['kind']) {
            return null;
        }

        if (isset($service['code'])) {
            $key = json_encode($service['key'], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);

            return \sprintf("tarteaucitron.services[%s] = {key:%s, js:function(){\n%s\n}, fallback:function(){}};", $key, $key, $service['code']);
        }

        if (!\in_array($service['kind'], ['script', 'media'], true) || 1 !== preg_match('/^[A-Za-z0-9_-]+$/', $service['key'])) {
            return null;
        }

        $fragment = $this->moduleDirectory.'/Resources/catalogue/fragments/'.$service['key'].'.js';

        return is_file($fragment) ? rtrim((string) file_get_contents($fragment)) : null;
    }

    private function read(string $relative): string
    {
        return rtrim((string) file_get_contents($this->moduleDirectory.'/'.$relative));
    }
}
