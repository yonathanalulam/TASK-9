<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DuplicateResolutionEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DuplicateResolutionEventRepository::class)]
#[ORM\Table(name: 'duplicate_resolution_events')]
#[ORM\Index(columns: ['target_content_item_id'], name: 'idx_dup_res_target')]
class DuplicateResolutionEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'uuid')]
    private Uuid $sourceImportItemId;

    #[ORM\Column(type: 'uuid')]
    private Uuid $targetContentItemId;

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $mergeType;

    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 4)]
    private string $similarityScore;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $mergedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $mergedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $unmergedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $unmergedBy = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $unmergeReason = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->mergedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSourceImportItemId(): Uuid
    {
        return $this->sourceImportItemId;
    }

    public function setSourceImportItemId(Uuid $sourceImportItemId): static
    {
        $this->sourceImportItemId = $sourceImportItemId;

        return $this;
    }

    public function getTargetContentItemId(): Uuid
    {
        return $this->targetContentItemId;
    }

    public function setTargetContentItemId(Uuid $targetContentItemId): static
    {
        $this->targetContentItemId = $targetContentItemId;

        return $this;
    }

    public function getMergeType(): string
    {
        return $this->mergeType;
    }

    public function setMergeType(string $mergeType): static
    {
        $this->mergeType = $mergeType;

        return $this;
    }

    public function getSimilarityScore(): string
    {
        return $this->similarityScore;
    }

    public function setSimilarityScore(string $similarityScore): static
    {
        $this->similarityScore = $similarityScore;

        return $this;
    }

    public function getMergedBy(): ?User
    {
        return $this->mergedBy;
    }

    public function setMergedBy(?User $mergedBy): static
    {
        $this->mergedBy = $mergedBy;

        return $this;
    }

    public function getMergedAt(): \DateTimeImmutable
    {
        return $this->mergedAt;
    }

    public function setMergedAt(\DateTimeImmutable $mergedAt): static
    {
        $this->mergedAt = $mergedAt;

        return $this;
    }

    public function getUnmergedAt(): ?\DateTimeImmutable
    {
        return $this->unmergedAt;
    }

    public function setUnmergedAt(?\DateTimeImmutable $unmergedAt): static
    {
        $this->unmergedAt = $unmergedAt;

        return $this;
    }

    public function getUnmergedBy(): ?User
    {
        return $this->unmergedBy;
    }

    public function setUnmergedBy(?User $unmergedBy): static
    {
        $this->unmergedBy = $unmergedBy;

        return $this;
    }

    public function getUnmergeReason(): ?string
    {
        return $this->unmergeReason;
    }

    public function setUnmergeReason(?string $unmergeReason): static
    {
        $this->unmergeReason = $unmergeReason;

        return $this;
    }
}
