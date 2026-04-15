<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Search\SearchIndexService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:search:index',
    description: 'Run incremental search index for content items',
)]
final class RunSearchIndexCommand extends Command
{
    public function __construct(
        private readonly SearchIndexService $searchIndexService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Starting incremental search index...');

        try {
            $job = $this->searchIndexService->runIncrementalIndex();
        } catch (\Throwable $e) {
            $io->error(sprintf('Indexing failed with exception: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->writeln(sprintf('Job ID: %s', $job->getId()->toRfc4122()));
        $io->writeln(sprintf('Status: %s', $job->getStatus()));
        $io->writeln(sprintf('Items processed: %d', $job->getItemsProcessed()));
        $io->writeln(sprintf('Items failed: %d', $job->getItemsFailed()));

        if ($job->getErrorDetail() !== null) {
            $io->warning('Errors:');
            $io->writeln($job->getErrorDetail());
        }

        if ($job->getStatus() === 'FAILED') {
            $io->error('Indexing job failed.');

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Indexing complete. %d item(s) processed, %d failed.',
            $job->getItemsProcessed(),
            $job->getItemsFailed(),
        ));

        return Command::SUCCESS;
    }
}
