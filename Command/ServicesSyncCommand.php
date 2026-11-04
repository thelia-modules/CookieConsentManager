<?php

declare(strict_types=1);

namespace CookieConsentManager\Command;

use CookieConsentManager\Proposed\ProposedServiceSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ccm:services:sync', description: 'Bring the services declared by modules (tag ccm.service) into the draft as proposed')]
final class ServicesSyncCommand extends Command
{
    public function __construct(private readonly ProposedServiceSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->synchronizer->synchronize();

        $output->writeln(\sprintf('added: %s', [] === $report['added'] ? '-' : implode(', ', $report['added'])));
        $output->writeln(\sprintf('updated: %s', [] === $report['updated'] ? '-' : implode(', ', $report['updated'])));
        foreach ($report['alerts'] as $alert) {
            $output->writeln('<comment>'.$alert.'</comment>');
        }

        return Command::SUCCESS;
    }
}
