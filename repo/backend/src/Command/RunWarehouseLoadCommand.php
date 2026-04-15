<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Warehouse\WarehouseLoadService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:warehouse:load',
    description: 'Run the warehouse ETL pipeline (dimensions + facts)',
)]
final class RunWarehouseLoadCommand extends Command
{
    public function __construct(
        private readonly WarehouseLoadService $loadService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Load type: FULL or INCREMENTAL', 'FULL');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $loadType = strtoupper((string) $input->getOption('type'));

        if (!in_array($loadType, ['FULL', 'INCREMENTAL'], true)) {
            $io->error('Invalid load type. Must be FULL or INCREMENTAL.');

            return Command::FAILURE;
        }

        $io->info(sprintf('Starting %s warehouse load...', $loadType));

        try {
            $run = $this->loadService->execute($loadType);
        } catch (\Throwable $e) {
            $io->error(sprintf('Warehouse load failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Warehouse load %s completed — Status: %s | Extracted: %d | Loaded: %d | Rejected: %d',
            $run->getId()->toRfc4122(),
            $run->getStatus(),
            $run->getRowsExtracted(),
            $run->getRowsLoaded(),
            $run->getRowsRejected(),
        ));

        return Command::SUCCESS;
    }
}
