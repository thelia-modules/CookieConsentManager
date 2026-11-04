<?php

declare(strict_types=1);

namespace CookieConsentManager\Preset;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PresetCatalogue
{
    /** @var array<string, Preset>|null */
    private ?array $presets = null;

    public function __construct(
        #[Autowire(value: __DIR__.'/../Resources/presets')]
        private readonly string $directory,
    ) {
    }

    public function find(string $code): ?Preset
    {
        return $this->all()[$code] ?? null;
    }

    /** @return array<string, Preset> by code */
    public function all(): array
    {
        if (null !== $this->presets) {
            return $this->presets;
        }

        $this->presets = [];
        $files = glob($this->directory.'/*.json') ?: [];
        sort($files);
        foreach ($files as $file) {
            $preset = Preset::fromDocument((array) json_decode((string) file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR));
            $this->presets[$preset->code] = $preset;
        }

        return $this->presets;
    }
}
