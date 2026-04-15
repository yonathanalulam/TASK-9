<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RetentionCaseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: RetentionCaseRepository::class)]
#[ORM\Table(name: 'retention_cases')]
#[ORM\UniqueConstraint(columns: ['entity_type', 'entity_id'])]
#[ORM\Index(columns: ['status', 'eligible_at'], name: 'idx_retention_cases_status_eligible')]
class RetentionCase
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $entityType;

    #[ORM\Column(type: Types::BINARY, length: 16)]
    private string $entityId;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'ELIGIBLE'])]
    private string $status = 'ELIGIBLE';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 365])]
    private int $retentionDays = 365;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $eligibleAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $executedAt = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $actionTaken = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorDetail = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
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

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): static
    {
        $this->entityId = $entityId;

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

    public function getRetentionDays(): int
    {
        return $this->retentionDays;
    }

    public function setRetentionDays(int $retentionDays): static
    {
        $this->retentionDays = $retentionDays;

        return $this;
    }

    public function getEligibleAt(): \DateTimeImmutable
    {
        return $this->eligibleAt;
    }

    public function setEligibleAt(\DateTimeImmutable $eligibleAt): static
    {
        $this->eligibleAt = $eligibleAt;

        return $this;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getExecutedAt(): ?\DateTimeImmutable
    {
        return $this->executedAt;
    }

    public function setExecutedAt(?\DateTimeImmutable $executedAt): static
    {
        $this->executedAt = $executedAt;

        return $this;
    }

    public function getActionTaken(): ?string
    {
        return $this->actionTaken;
    }

    public function setActionTaken(?string $actionTaken): static
    {
        $this->actionTaken = $actionTaken;

        return $this;
    }

    public function getErrorDetail(): ?string
    {
        return $this->errorDetail;
    }

    public function setErrorDetail(?string $errorDetail): static
    {
        $this->errorDetail = $errorDetail;

        return $this;
    }
}
