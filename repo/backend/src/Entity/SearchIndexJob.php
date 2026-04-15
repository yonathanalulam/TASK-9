<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SearchIndexJobRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SearchIndexJobRepository::class)]
#[ORM\Table(name: 'search_index_jobs')]
#[ORM\Index(columns: ['status'], name: 'idx_search_index_jobs_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_search_index_jobs_created_at')]
class SearchIndexJob
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'PENDING'])]
    private string $status = 'PENDING';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $itemsProcessed = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $itemsFailed = 0;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $lastIndexedVersionId = null;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getItemsProcessed(): int
    {
        return $this->itemsProcessed;
    }

    public function setItemsProcessed(int $itemsProcessed): static
    {
        $this->itemsProcessed = $itemsProcessed;

        return $this;
    }

    public function getItemsFailed(): int
    {
        return $this->itemsFailed;
    }

    public function setItemsFailed(int $itemsFailed): static
    {
        $this->itemsFailed = $itemsFailed;

        return $this;
    }

    public function getLastIndexedVersionId(): ?Uuid
    {
        return $this->lastIndexedVersionId;
    }

    public function setLastIndexedVersionId(?Uuid $lastIndexedVersionId): static
    {
        $this->lastIndexedVersionId = $lastIndexedVersionId;

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
