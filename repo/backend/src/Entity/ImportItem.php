<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ImportItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ImportItemRepository::class)]
#[ORM\Table(name: 'import_items')]
#[ORM\Index(columns: ['dedup_fingerprint'], name: 'idx_import_items_fingerprint')]
#[ORM\Index(columns: ['status'], name: 'idx_import_items_status')]
class ImportItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: ImportBatch::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ImportBatch $importBatch;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $rawTitle;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $rawCompany = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $rawLocation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rawBody = null;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $normalizedTitle;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $normalizedCompany = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $normalizedLocation = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $dedupFingerprint;

    #[ORM\Column(type: Types::STRING, length: 30, options: ['default' => 'NEW'])]
    private string $status = 'NEW';

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $matchedContentItemId = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 4, nullable: true)]
    private ?string $similarityScore = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getImportBatch(): ImportBatch
    {
        return $this->importBatch;
    }

    public function setImportBatch(ImportBatch $importBatch): static
    {
        $this->importBatch = $importBatch;

        return $this;
    }

    public function getRawTitle(): string
    {
        return $this->rawTitle;
    }

    public function setRawTitle(string $rawTitle): static
    {
        $this->rawTitle = $rawTitle;

        return $this;
    }

    public function getRawCompany(): ?string
    {
        return $this->rawCompany;
    }

    public function setRawCompany(?string $rawCompany): static
    {
        $this->rawCompany = $rawCompany;

        return $this;
    }

    public function getRawLocation(): ?string
    {
        return $this->rawLocation;
    }

    public function setRawLocation(?string $rawLocation): static
    {
        $this->rawLocation = $rawLocation;

        return $this;
    }

    public function getRawBody(): ?string
    {
        return $this->rawBody;
    }

    public function setRawBody(?string $rawBody): static
    {
        $this->rawBody = $rawBody;

        return $this;
    }

    public function getNormalizedTitle(): string
    {
        return $this->normalizedTitle;
    }

    public function setNormalizedTitle(string $normalizedTitle): static
    {
        $this->normalizedTitle = $normalizedTitle;

        return $this;
    }

    public function getNormalizedCompany(): ?string
    {
        return $this->normalizedCompany;
    }

    public function setNormalizedCompany(?string $normalizedCompany): static
    {
        $this->normalizedCompany = $normalizedCompany;

        return $this;
    }

    public function getNormalizedLocation(): ?string
    {
        return $this->normalizedLocation;
    }

    public function setNormalizedLocation(?string $normalizedLocation): static
    {
        $this->normalizedLocation = $normalizedLocation;

        return $this;
    }

    public function getDedupFingerprint(): string
    {
        return $this->dedupFingerprint;
    }

    public function setDedupFingerprint(string $dedupFingerprint): static
    {
        $this->dedupFingerprint = $dedupFingerprint;

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

    public function getMatchedContentItemId(): ?Uuid
    {
        return $this->matchedContentItemId;
    }

    public function setMatchedContentItemId(?Uuid $matchedContentItemId): static
    {
        $this->matchedContentItemId = $matchedContentItemId;

        return $this;
    }

    public function getSimilarityScore(): ?string
    {
        return $this->similarityScore;
    }

    public function setSimilarityScore(?string $similarityScore): static
    {
        $this->similarityScore = $similarityScore;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
