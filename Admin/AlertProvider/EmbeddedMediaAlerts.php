<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin\AlertProvider;

use CookieConsentManager\Admin\EmbeddedMediaAlert;

/** A video service active while the embedded iframes are not blocked. */
final readonly class EmbeddedMediaAlerts implements AlertProvider
{
    public function __construct(
        private EmbeddedMediaAlert $media,
        private AlertText $text,
    ) {
    }

    public static function priority(): int
    {
        return 800;
    }

    public function alerts(array $draft): array
    {
        $alerts = [];

        if (null !== $mediaAlert = $this->media->check($draft, $this->text->locale())) {
            $alerts[] = $mediaAlert;
        }

        return $alerts;
    }
}
