<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

use CookieConsentManager\Catalogue\CatalogueReader;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** A catalogue service that nobody has tested is labelled "experimental" wherever the back-office lists it. */
final class ExperimentalServiceTwigExtension extends AbstractExtension
{
    public function __construct(private readonly CatalogueReader $catalogue)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('ccm_is_experimental', $this->isExperimental(...))];
    }

    public function isExperimental(string $key): bool
    {
        $entry = $this->catalogue->find($key);

        return null !== $entry && !$entry->verified;
    }
}
