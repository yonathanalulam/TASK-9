<?php

declare(strict_types=1);

namespace App\Service\Governance;

use App\Entity\DataClassification;
use App\Entity\EncryptedFieldValue;
use App\Entity\EncryptionKey;
use App\Entity\User;
use App\Repository\DataClassificationRepository;
use App\Repository\EncryptedFieldValueRepository;
use App\Service\Audit\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Bridges DataClassification and EncryptionService:
 * encrypts field values classified as RESTRICTED or higher,
 * decrypts them on read for authorized users.
 */
class ClassifiedFieldService
{
    private const array ENCRYPTION_REQUIRED_LEVELS = ['RESTRICTED', 'PII', 'SENSITIVE'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EncryptionService $encryptionService,
        private readonly EncryptedFieldValueRepository $encryptedFieldValueRepository,
        private readonly DataClassificationRepository $classificationRepository,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * Store a classified field value. If the field's classification requires
     * encryption, the value is encrypted and stored in encrypted_field_values.
     *
     * @return bool True if the value was encrypted, false if stored as-is.
     */
    public function storeFieldValue(
        string $entityType,
        string $entityId,
        string $fieldName,
        string $plaintext,
        User $actor,
    ): bool {
        if (!$this->requiresEncryption($entityType, $fieldName)) {
            return false;
        }

        $result = $this->encryptionService->encrypt($plaintext);
        $key = $this->encryptionService->getActiveKey();

        // Upsert: find existing or create new.
        $existing = $this->findEncryptedFieldValue($entityType, $entityId, $fieldName);

        if ($existing !== null) {
            $existing->setEncryptedValue($result['encryptedValue']);
            $existing->setIv($result['iv']);
            $existing->setAuthTag($result['authTag']);
            $existing->setEncryptionKey($key);
        } else {
            $efv = new EncryptedFieldValue();
            $efv->setEntityType($entityType);
            $efv->setEntityId(Uuid::fromString($entityId)->toBinary());
            $efv->setFieldName($fieldName);
            $efv->setEncryptedValue($result['encryptedValue']);
            $efv->setIv($result['iv']);
            $efv->setAuthTag($result['authTag']);
            $efv->setEncryptionKey($key);
            $this->entityManager->persist($efv);
        }

        $this->entityManager->flush();

        $this->auditService->record(
            action: 'CLASSIFIED_FIELD_ENCRYPTED',
            entityType: $entityType,
            entityId: $entityId,
            oldValues: null,
            newValues: ['field_name' => $fieldName, 'key_id' => $result['keyId']],
            actor: $actor,
        );

        return true;
    }

    /**
     * Retrieve a classified field value. Decrypts if it was stored encrypted.
     *
     * @return string|null The plaintext value, or null if not found.
     */
    public function retrieveFieldValue(
        string $entityType,
        string $entityId,
        string $fieldName,
        User $actor,
    ): ?string {
        $efv = $this->findEncryptedFieldValue($entityType, $entityId, $fieldName);

        if ($efv === null) {
            return null;
        }

        $plaintext = $this->encryptionService->decrypt(
            $efv->getEncryptedValue(),
            $efv->getIv(),
            $efv->getAuthTag(),
            $efv->getEncryptionKey(),
        );

        $this->auditService->record(
            action: 'SENSITIVE_ACCESS',
            entityType: $entityType,
            entityId: $entityId,
            oldValues: null,
            newValues: ['field_name' => $fieldName, 'accessed_by' => $actor->getUsername()],
            actor: $actor,
        );

        return $plaintext;
    }

    /**
     * Check whether a field's classification requires encryption.
     */
    public function requiresEncryption(string $entityType, string $fieldName): bool
    {
        $classification = $this->classificationRepository->createQueryBuilder('dc')
            ->where('dc.entityType = :entityType')
            ->andWhere('dc.fieldName = :fieldName')
            ->setParameter('entityType', $entityType)
            ->setParameter('fieldName', $fieldName)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($classification === null) {
            return false;
        }

        return \in_array($classification->getClassification(), self::ENCRYPTION_REQUIRED_LEVELS, true);
    }

    private function findEncryptedFieldValue(
        string $entityType,
        string $entityId,
        string $fieldName,
    ): ?EncryptedFieldValue {
        $entityIdBinary = Uuid::fromString($entityId)->toBinary();

        return $this->encryptedFieldValueRepository->createQueryBuilder('efv')
            ->where('efv.entityType = :entityType')
            ->andWhere('efv.entityId = :entityId')
            ->andWhere('efv.fieldName = :fieldName')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityIdBinary)
            ->setParameter('fieldName', $fieldName)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
