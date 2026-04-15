<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Scraping\ScrapeOrchestratorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:scrape:run',
    description: 'Orchestrate scraping across all active source definitions',
)]
final class RunScrapeCommand extends Command
{
    public function __construct(
        private readonly ScrapeOrchestratorService $orchestrator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Starting scrape orchestration for all active sources...');

        try {
            $runs = $this->orchestrator->runAll();
        } catch (\Throwable $e) {
            $io->error(sprintf('Scrape orchestration failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        if (count($runs) === 0) {
            $io->warning('No active sources found to scrape.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Run ID', 'Source', 'Status', 'Found', 'New', 'Failed'],
            array_map(static fn ($run) => [
                $run->getId()->toRfc4122(),
                $run->getSourceDefinition()->getName(),
                $run->getStatus(),
                $run->getItemsFound(),
                $run->getItemsNew(),
                $run->getItemsFailed(),
            ], $runs),
        );

        $io->success(sprintf('Scrape orchestration complete. %d run(s) executed.', count($runs)));

        return Command::SUCCESS;
    }
}
