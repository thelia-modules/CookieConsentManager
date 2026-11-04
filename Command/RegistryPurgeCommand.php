<?php

declare(strict_types=1);

namespace CookieConsentManager\Command;

use CookieConsentManager\Registry\RegistryPurger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ccm:registry:purge', description: 'Delete the registry entries older than the retention period')]
final class RegistryPurgeCommand extends Command
{
    public function __construct(private readonly RegistryPurger $purger)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count without deleting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $count = $this->purger->purge(null, $dryRun);

        $output->writeln(\sprintf($dryRun ? '%d entries would be deleted.' : '%d entries deleted.', $count));

        return Command::SUCCESS;
    }
}
