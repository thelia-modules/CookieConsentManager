<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

final readonly class ContrastFinding
{
    public function __construct(
        public string $id,
        public string $field,
        public string $foreground,
        public string $background,
        public float $ratio,
        public float $minimum,
        public ?string $suggestion,
    ) {
    }

    public function passes(): bool
    {
        return $this->ratio >= $this->minimum;
    }
}
