<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AuditEventHash;
use App\Service\Audit\HashChainService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:audit:verify-chain',
    description: 'Verify the integrity of the audit event hash chain',
)]
final class VerifyAuditChainCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HashChainService $hashChainService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var AuditEventHash[] $hashRecords */
        $hashRecords = $this->entityManager->createQueryBuilder()
            ->select('h')
            ->from(AuditEventHash::class, 'h')
            ->orderBy('h.sequenceNumber', 'ASC')
            ->getQuery()
            ->getResult();

        if (\count($hashRecords) === 0) {
            $io->success('No audit records found. Nothing to verify.');
            return Command::SUCCESS;
        }

        $breaks = [];
        $previousRecord = null;

        foreach ($hashRecords as $index => $record) {
            $event = $record->getAuditEvent();
            $seq = $record->getSequenceNumber();

            // Re-compute the event hash from the associated AuditEvent
            $expectedEventHash = $this->hashChainService->computeEventHash($event);

            if ($record->getEventHash() !== $expectedEventHash) {
                $breaks[] = sprintf(
                    'Sequence %s: event_hash mismatch (stored: %s, computed: %s)',
                    $seq,
                    $record->getEventHash(),
                    $expectedEventHash,
                );
            }

            if ($index === 0) {
                // Genesis record
                if ($record->getPreviousHash() !== null) {
                    $breaks[] = sprintf(
                        'Sequence %s: genesis record has non-null previousHash: %s',
                        $seq,
                        $record->getPreviousHash(),
                    );
                }

                if ($record->getChainHash() !== $record->getEventHash()) {
                    $breaks[] = sprintf(
                        'Sequence %s: genesis chainHash (%s) does not equal eventHash (%s)',
                        $seq,
                        $record->getChainHash(),
                        $record->getEventHash(),
                    );
                }
            } else {
                // Non-genesis record
                /** @var AuditEventHash $previousRecord */
                $expectedPreviousHash = $previousRecord->getChainHash();

                if ($record->getPreviousHash() !== $expectedPreviousHash) {
                    $breaks[] = sprintf(
                        'Sequence %s: previousHash mismatch (stored: %s, expected: %s)',
                        $seq,
                        $record->getPreviousHash() ?? 'null',
                        $expectedPreviousHash,
                    );
                }

                $expectedChainHash = hash('sha256', $record->getPreviousHash() . $record->getEventHash());

                if ($record->getChainHash() !== $expectedChainHash) {
                    $breaks[] = sprintf(
                        'Sequence %s: chainHash mismatch (stored: %s, computed: %s)',
                        $seq,
                        $record->getChainHash(),
                        $expectedChainHash,
                    );
                }
            }

            $previousRecord = $record;
        }

        $total = \count($hashRecords);

        if (\count($breaks) === 0) {
            $io->success(sprintf('Verified %d audit records. Chain integrity: OK', $total));
            return Command::SUCCESS;
        }

        $io->error(sprintf('Verified %d audit records. Found %d break(s):', $total, \count($breaks)));

        foreach ($breaks as $break) {
            $io->writeln('  - ' . $break);
        }

        return Command::FAILURE;
    }
}
