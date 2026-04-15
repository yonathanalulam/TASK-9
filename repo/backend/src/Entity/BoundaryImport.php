<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BoundaryImportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: BoundaryImportRepository::class)]
#[ORM\Table(name: 'boundary_imports')]
#[ORM\Index(columns: ['status'], name: 'idx_boundary_imports_status')]
#[ORM\Index(columns: ['uploaded_by'], name: 'idx_boundary_imports_uploaded_by')]
class BoundaryImport
{
    public const STATUS_UPLOADED = 'UPLOADED';
    public const STATUS_VALIDATING = 'VALIDATING';
    public const STATUS_VALIDATED = 'VALIDATED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_APPLIED = 'APPLIED';
    public const STATUS_SUPERSEDED = 'SUPERSEDED';

    public const FILE_TYPE_GEOJSON = 'geojson';
    public const FILE_TYPE_SHAPEFILE_ZIP = 'shapefile_zip';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $fileName;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $fileType;

    #[ORM\Column(type: Types::INTEGER)]
    private int $fileSize;

    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    private string $fileHash;

    #[ORM\Column(type: Types::STRING, length: 512)]
    private string $storagePath;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'UPLOADED'])]
    private string $status = self::STATUS_UPLOADED;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $failureReason = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'uploaded_by', referencedColumnName: 'id', nullable: false)]
    private User $uploadedBy;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $validationErrors = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $appliedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): static
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getFileType(): string
    {
        return $this->fileType;
    }

    public function setFileType(string $fileType): static
    {
        $this->fileType = $fileType;

        return $this;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): static
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    public function getFileHash(): string
    {
        return $this->fileHash;
    }

    public function setFileHash(string $fileHash): static
    {
        $this->fileHash = $fileHash;

        return $this;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function setStoragePath(string $storagePath): static
    {
        $this->storagePath = $storagePath;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): static
    {
        $this->failureReason = $failureReason;

        return $this;
    }

    public function getUploadedBy(): User
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(User $uploadedBy): static
    {
        $this->uploadedBy = $uploadedBy;

        return $this;
    }

    public function getValidationErrors(): ?array
    {
        return $this->validationErrors;
    }

    public function setValidationErrors(?array $validationErrors): static
    {
        $this->validationErrors = $validationErrors;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getAppliedAt(): ?\DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function setAppliedAt(?\DateTimeImmutable $appliedAt): static
    {
        $this->appliedAt = $appliedAt;

        return $this;
    }
}
