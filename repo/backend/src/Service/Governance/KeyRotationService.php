<?php

declare(strict_types=1);

namespace App\Service\Governance;

use App\Entity\EncryptedFieldValue;
use App\Entity\EncryptionKey;
use App\Repository\EncryptedFieldValueRepository;
use App\Repository\EncryptionKeyRepository;
use Doctrine\ORM\EntityManagerInterface;

class KeyRotationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EncryptionKeyRepository $encryptionKeyRepository,
        private readonly EncryptedFieldValueRepository $encryptedFieldValueRepository,
        private readonly EncryptionService $encryptionService,
    ) {
    }

    /**
     * Initiate key rotation: create a new active key and mark the old one as ROTATING.
     */
    public function initiateRotation(): EncryptionKey
    {
        $currentKey = $this->encryptionKeyRepository->findOneBy(['status' => 'ACTIVE']);

        if ($currentKey !== null) {
            $currentKey->setStatus('ROTATING');
            $currentKey->setRotatedAt(new \DateTimeImmutable());
        }

        $newKey = new EncryptionKey();
        $newKey->setKeyAlias('dek-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)));
        $newKey->setEncryptedKeyMaterial($this->encryptionService->generateEncryptedKeyMaterial());
        $newKey->setStatus('ACTIVE');
        $newKey->setExpiresAt(new \DateTimeImmutable('+365 days'));

        $this->entityManager->persist($newKey);
        $this->entityManager->flush();

        return $newKey;
    }

    /**
     * Re-encrypt a batch of encrypted field values from the old (ROTATING) key to the new (ACTIVE) key.
     *
     * @return int Number of records re-encrypted in this batch.
     */
    public function reEncryptBatch(int $batchSize): int
    {
        $rotatingKey = $this->encryptionKeyRepository->findOneBy(['status' => 'ROTATING']);

        if ($rotatingKey === null) {
            return 0;
        }

        $activeKey = $this->encryptionKeyRepository->findOneBy(['status' => 'ACTIVE']);

        if ($activeKey === null) {
            throw new \RuntimeException('No active encryption key found for re-encryption.');
        }

        /** @var EncryptedFieldValue[] $records */
        $records = $this->encryptedFieldValueRepository->createQueryBuilder('efv')
            ->where('efv.encryptionKey = :oldKey')
            ->setParameter('oldKey', $rotatingKey)
            ->setMaxResults($batchSize)
            ->getQuery()
            ->getResult();

        $count = 0;

        foreach ($records as $record) {
            $plaintext = $this->encryptionService->decrypt(
                $record->getEncryptedValue(),
                $record->getIv(),
                $record->getAuthTag(),
                $rotatingKey,
            );

            $result = $this->encryptionService->encrypt($plaintext);

            $record->setEncryptedValue($result['encryptedValue']);
            $record->setIv($result['iv']);
            $record->setAuthTag($result['authTag']);
            $record->setEncryptionKey($activeKey);

            $count++;
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * Complete rotation: mark the ROTATING key as RETIRED.
     */
    public function completeRotation(): void
    {
        $rotatingKey = $this->encryptionKeyRepository->findOneBy(['status' => 'ROTATING']);

        if ($rotatingKey === null) {
            throw new \RuntimeException('No key currently in ROTATING status.');
        }

        // Verify no records still use the rotating key
        $remainingCount = $this->encryptedFieldValueRepository->createQueryBuilder('efv')
            ->select('COUNT(efv.id)')
            ->where('efv.encryptionKey = :oldKey')
            ->setParameter('oldKey', $rotatingKey)
            ->getQuery()
            ->getSingleScalarResult();

        if ((int) $remainingCount > 0) {
            throw new \RuntimeException(sprintf(
                'Cannot complete rotation: %d records still use the rotating key.',
                $remainingCount,
            ));
        }

        $rotatingKey->setStatus('RETIRED');
        $this->entityManager->flush();
    }
}
