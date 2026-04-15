<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:search:compact',
    description: 'Weekly search index compaction — OPTIMIZE TABLE without blocking reads',
)]
final class IndexCompactionCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->info('Starting search index compaction...');

        try {
            // InnoDB OPTIMIZE TABLE performs online DDL — reads are NOT blocked.
            // It rebuilds the table and reclaims fragmented space.
            $this->connection->executeStatement('OPTIMIZE TABLE content_search_index');

            $io->success('Search index compaction completed successfully.');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Compaction failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
