<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Entity\AuditEvent;
use App\Entity\AuditEventHash;
use App\Repository\AuditEventHashRepository;
use Doctrine\ORM\EntityManagerInterface;

class HashChainService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditEventHashRepository $auditEventHashRepository,
    ) {
    }

    public function computeAndStore(AuditEvent $event): AuditEventHash
    {
        $eventHash = $this->computeEventHash($event);

        $previous = $this->auditEventHashRepository->findOneBy(
            [],
            ['sequenceNumber' => 'DESC'],
        );

        if ($previous === null) {
            $previousHash = null;
            $chainHash = $eventHash;
        } else {
            /** @var AuditEventHash $previous */
            $previousHash = $previous->getChainHash();
            $chainHash = hash('sha256', $previousHash . $eventHash);
        }

        $hashRecord = new AuditEventHash();
        $hashRecord->setAuditEvent($event);
        $hashRecord->setSequenceNumber($event->getSequenceNumber());
        $hashRecord->setPreviousHash($previousHash);
        $hashRecord->setEventHash($eventHash);
        $hashRecord->setChainHash($chainHash);
        $hashRecord->setComputedAt(new \DateTimeImmutable());

        $this->entityManager->persist($hashRecord);

        return $hashRecord;
    }

    /**
     * Compute a SHA-256 hash of the canonical JSON representation of an audit event.
     */
    public function computeEventHash(AuditEvent $event): string
    {
        $data = [
            'action' => $event->getAction(),
            'entityType' => $event->getEntityType(),
            'entityId' => bin2hex($event->getEntityId()),
            'actorUsername' => $event->getActorUsername(),
            'occurredAt' => $event->getOccurredAt()->format(\DateTimeInterface::ATOM),
            'oldValues' => $event->getOldValues(),
            'newValues' => $event->getNewValues(),
        ];

        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $json);
    }
}
