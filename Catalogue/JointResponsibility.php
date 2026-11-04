<?php

declare(strict_types=1);

namespace CookieConsentManager\Catalogue;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Catalogue services whose publisher is a joint controller with the site for the collection and transmission of the data (sources in REPORT.md). */
class JointResponsibility
{
    /** @var list<string>|null */
    private ?array $keys = null;

    public function __construct(
        #[Autowire(value: __DIR__.'/../Resources/catalogue/joint-responsibility.json')]
        private readonly string $path,
    ) {
    }

    public function applies(string $key): bool
    {
        if (null === $this->keys) {
            $content = is_file($this->path) ? file_get_contents($this->path) : false;
            $this->keys = false === $content ? [] : array_map('strval', (array) json_decode($content, true, 512, \JSON_THROW_ON_ERROR));
        }

        return \in_array($key, $this->keys, true);
    }

    /** @param list<array<string, mixed>> $services resolved services of a published snapshot */
    public function among(array $services): bool
    {
        foreach ($services as $service) {
            if (!isset($service['custom']) && !isset($service['module']) && $this->applies((string) $service['key'])) {
                return true;
            }
        }

        return false;
    }
}
