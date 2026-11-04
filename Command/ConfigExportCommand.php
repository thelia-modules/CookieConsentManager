<?php

declare(strict_types=1);

namespace CookieConsentManager\Command;

use CookieConsentManager\Transfer\ConfigExporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ccm:config:export', description: 'Export the draft configuration as JSON (schema ccm-config/1)')]
final class ConfigExportCommand extends Command
{
    public function __construct(private readonly ConfigExporter $exporter)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'File to write; standard output when omitted');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = ConfigExporter::toJson($this->exporter->export());
        $file = $input->getOption('output');

        if (\is_string($file)) {
            file_put_contents($file, $json);
            $output->writeln(\sprintf('<info>Configuration exported to %s</info>', $file));

            return Command::SUCCESS;
        }

        $output->write($json, false, OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
