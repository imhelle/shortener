<?php

namespace App\Command;

use App\Service\LinkShortenerInterface;
use app\src\Service\Exception\LinkShortenerException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:shorten',
    description: 'Shorten a link to a specific URL',
)]
class ShortenCommand extends Command
{

    public function __construct(private readonly LinkShortenerInterface $linkShortener)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('url', InputArgument::REQUIRED, 'URL to shorten');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $url = $input->getArgument('url');

        try {
            $result = $this->linkShortener->shorten($url);
            $io->success("{$url} has been shortened to {$result}");
            return Command::SUCCESS;
        } catch (LinkShortenerException $e) {
            $io->error($e->getMessage());
        }
        return Command::FAILURE;
    }
}
