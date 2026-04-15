<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Governance\RetentionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:retention:scan',
    description: 'Scan for entities past their retention period and create eligible retention cases',
)]
final class RetentionScanCommand extends Command
{
    public function __construct(
        private readonly RetentionService $retentionService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Scanning for entities past their retention period...');

        try {
            $count = $this->retentionService->scanEligible();
        } catch (\Throwable $e) {
            $io->error(sprintf('Retention scan failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(sprintf('Retention scan complete. %d new eligible case(s) created.', $count));

        return Command::SUCCESS;
    }
}
