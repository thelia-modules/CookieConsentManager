<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin;

final readonly class Alert
{
    public const DANGER = 'danger';
    public const WARNING = 'warning';
    public const INFO = 'info';

    /**
     * @param list<string>          $details         technical elements (files, commands) kept out of the sentence, shown on demand
     * @param array<string, string> $routeParameters
     */
    public function __construct(
        public string $severity,
        public string $code,
        public string $message,
        public ?string $route = null,
        public array $details = [],
        public array $routeParameters = [],
        public ?string $fragment = null,
    ) {
    }
}
