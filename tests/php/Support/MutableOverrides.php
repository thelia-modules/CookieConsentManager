<?php

declare(strict_types=1);

namespace CookieConsentManager\Tests\Support;

use CookieConsentManager\Publication\OverriddenTemplatesProvider;

/** A theme that gets (or loses) an override while the module runs. */
final class MutableOverrides implements OverriddenTemplatesProvider
{
    public int $lists = 0;
    public bool $overridden = false;

    public function list(): array
    {
        ++$this->lists;

        return $this->overridden ? [['template' => 'banner.html.twig', 'source' => 'child', 'hash' => 'abcdef012345', 'contract' => '1']] : [];
    }

    public function signature(): string
    {
        return $this->overridden ? 'banner.html.twig|1' : '';
    }
}
