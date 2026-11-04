<?php

declare(strict_types=1);

namespace CookieConsentManager\Admin\AlertProvider;

use CookieConsentManager\Admin\Alert;
use CookieConsentManager\Registry\LastPurgeStore;
use CookieConsentManager\Registry\RegistryPurger;

/** The registry must be purged at least every 7 days. */
final readonly class RegistryPurgeAlerts implements AlertProvider
{
    public function __construct(
        private RegistryPurger $purger,
        private LastPurgeStore $lastPurge,
        private AlertText $text,
    ) {
    }

    public static function priority(): int
    {
        return 150;
    }

    public function alerts(array $draft): array
    {
        $alerts = [];

        if ($this->purger->isLate()) {
            $last = $this->lastPurge->read();
            $alerts[] = new Alert(Alert::DANGER, 'purge_late', null === $last
                ? $this->text->t('The registry was never purged: purge it from the registry screen, or have a scheduled purge set up.')
                : $this->text->t('The registry was last purged on %date% (more than 7 days ago): purge it from the registry screen, or have a scheduled purge set up.', ['%date%' => $last->format('Y-m-d')]), 'ccm_admin_registry', ['ccm:registry:purge']);
        }

        return $alerts;
    }
}
