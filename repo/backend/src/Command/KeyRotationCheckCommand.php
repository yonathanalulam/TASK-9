<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\EncryptionKey;
use App\Service\Governance\KeyRotationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:keys:check-rotation',
    description: 'Check if any active encryption key is older than 90 days and initiate rotation if needed',
)]
final class KeyRotationCheckCommand extends Command
{
    private const int MAX_AGE_DAYS = 90;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KeyRotationService $keyRotationService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Checking active encryption keys for rotation eligibility...');

        /** @var EncryptionKey[] $activeKeys */
        $activeKeys = $this->entityManager->getRepository(EncryptionKey::class)->findBy(['status' => 'ACTIVE']);

        if (\count($activeKeys) === 0) {
            $io->warning('No active encryption keys found.');

            return Command::SUCCESS;
        }

        $threshold = new \DateTimeImmutable(sprintf('-%d days', self::MAX_AGE_DAYS));
        $rotationNeeded = false;

        foreach ($activeKeys as $key) {
            $age = $key->getCreatedAt();
            $daysSinceCreation = (int) $age->diff(new \DateTimeImmutable())->days;

            if ($age < $threshold) {
                $io->warning(sprintf(
                    'Key "%s" is %d days old (threshold: %d days). Initiating rotation...',
                    $key->getKeyAlias(),
                    $daysSinceCreation,
                    self::MAX_AGE_DAYS,
                ));

                try {
                    $newKey = $this->keyRotationService->initiateRotation();
                    $io->success(sprintf(
                        'Rotation initiated. New key: "%s". Old key marked as ROTATING.',
                        $newKey->getKeyAlias(),
                    ));
                    $rotationNeeded = true;
                } catch (\Throwable $e) {
                    $io->error(sprintf('Key rotation failed: %s', $e->getMessage()));

                    return Command::FAILURE;
                }
            } else {
                $io->info(sprintf(
                    'Key "%s" is %d days old. No rotation needed.',
                    $key->getKeyAlias(),
                    $daysSinceCreation,
                ));
            }
        }

        if (!$rotationNeeded) {
            $io->success('All active keys are within the rotation threshold. No action taken.');
        }

        return Command::SUCCESS;
    }
}
