<?php

declare(strict_types=1);

namespace CookieConsentManager\Command;

use CookieConsentManager\Transfer\ConfigImporter;
use CookieConsentManager\Transfer\ImportRefusedException;
use CookieConsentManager\Validation\ValidationIssue;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ccm:config:import',
    description: 'Import a configuration file into the draft (never publishes)',
    help: 'Exit code 0: the draft was created (fields still to confirm are listed as warnings, they only block the publication). Non-zero: the file was unreadable or refused, nothing was imported.',
)]
final class ConfigImportCommand extends Command
{
    public function __construct(private readonly ConfigImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'JSON file produced by ccm:config:export');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = (string) $input->getArgument('file');
        $json = is_readable($file) ? file_get_contents($file) : false;
        if (false === $json) {
            $output->writeln(\sprintf('<error>Cannot read %s</error>', $file));

            return Command::FAILURE;
        }

        try {
            $report = $this->importer->import($json, 'console');
        } catch (ImportRefusedException $exception) {
            $output->writeln('<error>Import refused:</error>');
            foreach ($exception->violations as $violation) {
                $output->writeln('  - '.$violation);
            }

            return Command::FAILURE;
        }

        $output->writeln('<info>Draft created from the file; nothing is published.</info>');
        foreach ($report->skippedServices as $key) {
            $output->writeln(\sprintf('  skipped experimental service: %s', $key));
        }
        if ([] !== $report->environmentPending) {
            $output->writeln('<comment>Environment fields to confirm before publishing (ccm:config:publish --confirm-environment):</comment>');
            foreach ($report->environmentPending as $field) {
                $output->writeln('  - '.$field);
            }
        }
        if (false === $report->policyResolved) {
            $output->writeln('<comment>The privacy policy URL matches no page of this site.</comment>');
        }
        foreach ($report->issues as $issue) {
            $severity = 'environment_unconfirmed' === $issue->code ? ValidationIssue::WARNING : $issue->severity;
            $output->writeln(\sprintf('  [%s] %s: %s', $severity, $issue->field, $issue->message));
        }

        return Command::SUCCESS;
    }
}
