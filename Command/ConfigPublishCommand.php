<?php

declare(strict_types=1);

namespace CookieConsentManager\Command;

use CookieConsentManager\Draft\DraftRepository;
use CookieConsentManager\Publication\NothingToPublishException;
use CookieConsentManager\Publication\PublicationRefusedException;
use CookieConsentManager\Publication\Publisher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ccm:config:publish', description: 'Publish the draft as a new immutable version')]
final class ConfigPublishCommand extends Command
{
    public function __construct(
        private readonly Publisher $publisher,
        private readonly DraftRepository $drafts,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('confirm-environment', null, InputOption::VALUE_NONE, 'Confirm the environment fields of an import')
            ->addOption('substantial', null, InputOption::VALUE_NONE, 'Substantial change (texts, controllers): the banner is shown again to everyone')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Publish even when nothing changed since the latest version')
            ->addOption('comment', null, InputOption::VALUE_REQUIRED, 'Comment stored with the version');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('confirm-environment')) {
            $this->drafts->confirmEnvironment();
        }

        try {
            $comment = $input->getOption('comment');
            $version = $this->publisher->publish('console', (bool) $input->getOption('substantial'), \is_string($comment) ? $comment : null, !$input->getOption('force'));
        } catch (NothingToPublishException $exception) {
            $output->writeln(\sprintf('<error>Nothing to publish:</error> %s', $exception->getMessage()));
            $output->writeln('Use --force to publish a new version anyway (an identical configuration gives an identical version).');

            return Command::FAILURE;
        } catch (PublicationRefusedException $exception) {
            $output->writeln('<error>Publication refused:</error>');
            $output->writeln($exception->getMessage());

            return Command::FAILURE;
        }

        $output->writeln(\sprintf('<info>Version %d published (served hash %s).</info>', $version->getVersion(), $version->getServedHash()));

        return Command::SUCCESS;
    }
}
