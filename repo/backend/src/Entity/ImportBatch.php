<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ImportBatchRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ImportBatchRepository::class)]
#[ORM\Table(name: 'import_batches')]
class ImportBatch
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $sourceName;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $fileName = null;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'PENDING'])]
    private string $status = 'PENDING';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $totalItems = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $processedItems = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $mergedItems = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $reviewItems = 0;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSourceName(): string
    {
        return $this->sourceName;
    }

    public function setSourceName(string $sourceName): static
    {
        $this->sourceName = $sourceName;

        return $this;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(?string $fileName): static
    {
        $this->fileName = $fileName;

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

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function setTotalItems(int $totalItems): static
    {
        $this->totalItems = $totalItems;

        return $this;
    }

    public function getProcessedItems(): int
    {
        return $this->processedItems;
    }

    public function setProcessedItems(int $processedItems): static
    {
        $this->processedItems = $processedItems;

        return $this;
    }

    public function getMergedItems(): int
    {
        return $this->mergedItems;
    }

    public function setMergedItems(int $mergedItems): static
    {
        $this->mergedItems = $mergedItems;

        return $this;
    }

    public function getReviewItems(): int
    {
        return $this->reviewItems;
    }

    public function setReviewItems(int $reviewItems): static
    {
        $this->reviewItems = $reviewItems;

        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
}
