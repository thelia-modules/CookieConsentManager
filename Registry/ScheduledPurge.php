<?php

declare(strict_types=1);

namespace CookieConsentManager\Registry;

use Symfony\Component\Scheduler\Attribute\AsCronTask;

/**
 * Registered only where the scheduler of Thelia exists (3.3+), see CookieConsentManager::configureServices().
 * Runs only with a worker consuming `scheduler_thelia`; `ccm:registry:purge` from a crontab is the universal way.
 */
#[AsCronTask('17 3 * * *', schedule: 'thelia')]
final readonly class ScheduledPurge
{
    public function __construct(private RegistryPurger $purger)
    {
    }

    public function __invoke(): void
    {
        $this->purger->purge();
    }
}
