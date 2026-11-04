<?php

declare(strict_types=1);

namespace CookieConsentManager\EventListener;

use CookieConsentManager\Proposed\ProposedServiceSynchronizer;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** Brings the services declared by other modules into the draft after `module:refresh`. */
final readonly class ModuleRefreshSyncListener
{
    public function __construct(private ProposedServiceSynchronizer $synchronizer)
    {
    }

    #[AsEventListener(event: ConsoleEvents::TERMINATE)]
    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        if ('module:refresh' !== $event->getCommand()?->getName() || 0 !== $event->getExitCode()) {
            return;
        }

        try {
            $this->synchronizer->synchronize();
        } catch (\Throwable $exception) {
            $event->getOutput()->writeln('<comment>CookieConsentManager: synchronisation of declared services skipped ('.$exception->getMessage().')</comment>');
        }
    }
}
