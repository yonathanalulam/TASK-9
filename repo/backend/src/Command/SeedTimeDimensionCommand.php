<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Warehouse\TimeDimensionSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:warehouse:seed-time',
    description: 'Seed the wh_dim_time table with date rows from 2024-01-01 to 2028-12-31',
)]
final class SeedTimeDimensionCommand extends Command
{
    public function __construct(
        private readonly TimeDimensionSeeder $seeder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $from = new \DateTimeImmutable('2024-01-01');
        $to = new \DateTimeImmutable('2028-12-31');

        $io->info(sprintf('Seeding time dimension from %s to %s...', $from->format('Y-m-d'), $to->format('Y-m-d')));

        try {
            $count = $this->seeder->seed($from, $to);
        } catch (\Throwable $e) {
            $io->error(sprintf('Time dimension seeding failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(sprintf('Time dimension seeded successfully. %d row(s) inserted.', $count));

        return Command::SUCCESS;
    }
}
