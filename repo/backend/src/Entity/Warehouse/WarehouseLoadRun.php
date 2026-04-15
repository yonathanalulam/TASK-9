<?php

declare(strict_types=1);

namespace App\Entity\Warehouse;

use App\Repository\Warehouse\WarehouseLoadRunRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: WarehouseLoadRunRepository::class)]
#[ORM\Table(name: 'wh_load_runs')]
class WarehouseLoadRun
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $loadType;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $sourceTables = [];

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'PENDING'])]
    private string $status = 'PENDING';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $rowsExtracted = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $rowsLoaded = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $rowsRejected = 0;

    /** @var array<int, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $rejectedDetail = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorDetail = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getLoadType(): string
    {
        return $this->loadType;
    }

    public function setLoadType(string $loadType): static
    {
        $this->loadType = $loadType;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getSourceTables(): array
    {
        return $this->sourceTables;
    }

    /** @param array<string, mixed> $sourceTables */
    public function setSourceTables(array $sourceTables): static
    {
        $this->sourceTables = $sourceTables;

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

    public function getRowsExtracted(): int
    {
        return $this->rowsExtracted;
    }

    public function setRowsExtracted(int $rowsExtracted): static
    {
        $this->rowsExtracted = $rowsExtracted;

        return $this;
    }

    public function getRowsLoaded(): int
    {
        return $this->rowsLoaded;
    }

    public function setRowsLoaded(int $rowsLoaded): static
    {
        $this->rowsLoaded = $rowsLoaded;

        return $this;
    }

    public function getRowsRejected(): int
    {
        return $this->rowsRejected;
    }

    public function setRowsRejected(int $rowsRejected): static
    {
        $this->rowsRejected = $rowsRejected;

        return $this;
    }

    /** @return array<int, mixed>|null */
    public function getRejectedDetail(): ?array
    {
        return $this->rejectedDetail;
    }

    /** @param array<int, mixed>|null $rejectedDetail */
    public function setRejectedDetail(?array $rejectedDetail): static
    {
        $this->rejectedDetail = $rejectedDetail;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
