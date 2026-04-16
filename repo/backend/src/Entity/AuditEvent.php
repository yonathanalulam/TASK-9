<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuditEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AuditEventRepository::class)]
#[ORM\Table(name: 'audit_events')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_audit_events_entity')]
#[ORM\Index(columns: ['actor_id'], name: 'idx_audit_events_actor_id')]
#[ORM\Index(columns: ['occurred_at'], name: 'idx_audit_events_occurred_at')]
#[ORM\Index(columns: ['action'], name: 'idx_audit_events_action')]
class AuditEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::BIGINT, unique: true)]
    private string $sequenceNumber;

    #[ORM\Column(name: 'actor_id', type: Types::BINARY, length: 16, nullable: true)]
    private ?string $actorId = null;

    #[ORM\Column(type: Types::STRING, length: 80, nullable: true)]
    private ?string $actorUsername = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $action;

    #[ORM\Column(type: Types::STRING, length: 80)]
    private string $entityType;

    #[ORM\Column(name: 'entity_id', type: Types::BINARY, length: 16)]
    private string $entityId;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $oldValues = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $newValues = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        string $sequenceNumber,
        string $action,
        string $entityType,
        string $entityId,
        \DateTimeImmutable $occurredAt,
        ?string $actorId = null,
        ?string $actorUsername = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ) {
        $this->id = Uuid::v7();
        $this->sequenceNumber = $sequenceNumber;
        $this->action = $action;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->occurredAt = $occurredAt;
        $this->actorId = $actorId;
        $this->actorUsername = $actorUsername;
        $this->oldValues = $oldValues;
        $this->newValues = $newValues;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSequenceNumber(): string
    {
        return $this->sequenceNumber;
    }

    public function getActorId(): ?string
    {
        return $this->actorId;
    }

    public function getActorUsername(): ?string
    {
        return $this->actorUsername;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getOldValues(): ?array
    {
        return $this->oldValues;
    }

    public function getNewValues(): ?array
    {
        return $this->newValues;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
