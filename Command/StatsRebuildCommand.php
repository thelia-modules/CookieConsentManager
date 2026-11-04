<?php

declare(strict_types=1);

namespace CookieConsentManager\Command;

use CookieConsentManager\Registry\DailyStatRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ccm:stats:rebuild', description: 'Recompute the first-choice statistics from the registry (the registry is never modified)')]
final class StatsRebuildCommand extends Command
{
    public function __construct(private readonly DailyStatRepository $stats)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(\sprintf('%d first choices counted from the registry.', $this->stats->rebuildFromRegistry()));

        return Command::SUCCESS;
    }
}
