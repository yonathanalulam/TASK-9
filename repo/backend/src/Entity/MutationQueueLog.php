<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MutationQueueLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MutationQueueLogRepository::class)]
#[ORM\Table(name: 'mutation_queue_log')]
#[ORM\Index(columns: ['client_id'], name: 'idx_mutation_queue_log_client_id')]
#[ORM\Index(columns: ['status'], name: 'idx_mutation_queue_log_status')]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_mutation_queue_log_entity')]
class MutationQueueLog
{
    public const STATUS_RECEIVED = 'RECEIVED';
    public const STATUS_APPLIED = 'APPLIED';
    public const STATUS_CONFLICT = 'CONFLICT';
    public const STATUS_REJECTED = 'REJECTED';

    public const OPERATION_CREATE = 'CREATE';
    public const OPERATION_UPDATE = 'UPDATE';
    public const OPERATION_DELETE = 'DELETE';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $clientId;

    #[ORM\Column(type: Types::STRING, length: 36, unique: true)]
    private string $mutationId;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $entityType;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $entityId = null;

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $operation;

    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'RECEIVED'])]
    private string $status = self::STATUS_RECEIVED;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conflictDetail = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->receivedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): static
    {
        $this->clientId = $clientId;

        return $this;
    }

    public function getMutationId(): string
    {
        return $this->mutationId;
    }

    public function setMutationId(string $mutationId): static
    {
        $this->mutationId = $mutationId;

        return $this;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(?string $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function setOperation(string $operation): static
    {
        $this->operation = $operation;

        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getConflictDetail(): ?string
    {
        return $this->conflictDetail;
    }

    public function setConflictDetail(?string $conflictDetail): static
    {
        $this->conflictDetail = $conflictDetail;

        return $this;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): static
    {
        $this->processedAt = $processedAt;

        return $this;
    }
}
