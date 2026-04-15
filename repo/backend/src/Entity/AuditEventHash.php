<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuditEventHashRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AuditEventHashRepository::class)]
#[ORM\Table(name: 'audit_event_hashes')]
#[ORM\UniqueConstraint(name: 'uq_audit_event_hash_event', columns: ['audit_event_id'])]
#[ORM\UniqueConstraint(name: 'uq_audit_event_hash_sequence', columns: ['sequence_number'])]
class AuditEventHash
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: AuditEvent::class)]
    #[ORM\JoinColumn(name: 'audit_event_id', referencedColumnName: 'id', nullable: false)]
    private AuditEvent $auditEvent;

    #[ORM\Column(type: Types::BIGINT)]
    private string $sequenceNumber;

    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    private ?string $previousHash = null;

    #[ORM\Column(type: Types::STRING, length: 128)]
    private string $eventHash;

    #[ORM\Column(type: Types::STRING, length: 128)]
    private string $chainHash;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $computedAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->computedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getAuditEvent(): AuditEvent
    {
        return $this->auditEvent;
    }

    public function setAuditEvent(AuditEvent $auditEvent): static
    {
        $this->auditEvent = $auditEvent;

        return $this;
    }

    public function getSequenceNumber(): string
    {
        return $this->sequenceNumber;
    }

    public function setSequenceNumber(string $sequenceNumber): static
    {
        $this->sequenceNumber = $sequenceNumber;

        return $this;
    }

    public function getPreviousHash(): ?string
    {
        return $this->previousHash;
    }

    public function setPreviousHash(?string $previousHash): static
    {
        $this->previousHash = $previousHash;

        return $this;
    }

    public function getEventHash(): string
    {
        return $this->eventHash;
    }

    public function setEventHash(string $eventHash): static
    {
        $this->eventHash = $eventHash;

        return $this;
    }

    public function getChainHash(): string
    {
        return $this->chainHash;
    }

    public function setChainHash(string $chainHash): static
    {
        $this->chainHash = $chainHash;

        return $this;
    }

    public function getComputedAt(): \DateTimeImmutable
    {
        return $this->computedAt;
    }

    public function setComputedAt(\DateTimeImmutable $computedAt): static
    {
        $this->computedAt = $computedAt;

        return $this;
    }
}
