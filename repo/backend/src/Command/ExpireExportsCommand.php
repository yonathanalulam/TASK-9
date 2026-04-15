<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ExportJob;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:exports:expire',
    description: 'Mark SUCCEEDED exports older than 7 days as EXPIRED',
)]
final class ExpireExportsCommand extends Command
{
    private const int EXPIRY_DAYS = 7;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Scanning for expired export jobs...');

        $cutoff = new \DateTimeImmutable(sprintf('-%d days', self::EXPIRY_DAYS));

        try {
            $expiredCount = $this->entityManager->createQueryBuilder()
                ->update(ExportJob::class, 'j')
                ->set('j.status', ':newStatus')
                ->where('j.status = :currentStatus')
                ->andWhere('j.completedAt < :cutoff')
                ->setParameter('newStatus', 'EXPIRED')
                ->setParameter('currentStatus', 'SUCCEEDED')
                ->setParameter('cutoff', $cutoff)
                ->getQuery()
                ->execute();
        } catch (\Throwable $e) {
            $io->error(sprintf('Export expiry failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(sprintf('Export expiry complete. %d export(s) marked as EXPIRED.', $expiredCount));

        return Command::SUCCESS;
    }
}
