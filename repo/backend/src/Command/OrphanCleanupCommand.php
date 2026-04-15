<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Search\OrphanCleanupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:search:cleanup',
    description: 'Remove search index entries for content archived more than 14 days ago',
)]
final class OrphanCleanupCommand extends Command
{
    public function __construct(
        private readonly OrphanCleanupService $orphanCleanupService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Running orphan cleanup for archived content...');

        try {
            $deleted = $this->orphanCleanupService->cleanup();
        } catch (\Throwable $e) {
            $io->error(sprintf('Cleanup failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(sprintf('Cleanup complete. %d search index entries removed.', $deleted));

        return Command::SUCCESS;
    }
}
