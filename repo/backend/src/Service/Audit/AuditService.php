<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Entity\AuditEvent;
use App\Entity\User;
use App\Repository\AuditEventRepository;
use Doctrine\ORM\EntityManagerInterface;

class AuditService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditEventRepository $auditEventRepository,
        private readonly HashChainService $hashChainService,
    ) {
    }

    public function record(
        string $action,
        string $entityType,
        string $entityId,
        ?array $oldValues,
        ?array $newValues,
        ?User $actor = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuditEvent {
        $maxSequence = $this->entityManager->createQueryBuilder()
            ->select('MAX(e.sequenceNumber)')
            ->from(AuditEvent::class, 'e')
            ->getQuery()
            ->getSingleScalarResult();

        $nextSequence = $maxSequence !== null ? (string) ((int) $maxSequence + 1) : '1';

        $actorId = $actor?->getId()->toBinary();
        $actorUsername = $actor?->getUsername();

        // Convert RFC4122 UUID string to binary for BINARY(16) column storage.
        $entityIdBinary = $entityId;
        if (\strlen($entityId) === 36 && str_contains($entityId, '-')) {
            try {
                $entityIdBinary = \Symfony\Component\Uid\Uuid::fromString($entityId)->toBinary();
            } catch (\Throwable) {
                // Not a UUID — store as-is (will be truncated to 16 bytes by DB)
            }
        }

        $event = new AuditEvent(
            sequenceNumber: $nextSequence,
            action: $action,
            entityType: $entityType,
            entityId: $entityIdBinary,
            occurredAt: new \DateTimeImmutable(),
            actorId: $actorId,
            actorUsername: $actorUsername,
            oldValues: $oldValues,
            newValues: $newValues,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        $this->entityManager->persist($event);
        $this->hashChainService->computeAndStore($event);
        $this->entityManager->flush();

        return $event;
    }
}
