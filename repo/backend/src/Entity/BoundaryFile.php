<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BoundaryFileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: BoundaryFileRepository::class)]
#[ORM\Table(name: 'boundary_files')]
class BoundaryFile
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $entityType;

    #[ORM\Column(name: 'entity_id', type: Types::BINARY, length: 16)]
    private string $entityId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $fileName;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $filePath;

    #[ORM\Column(type: Types::STRING, length: 128)]
    private string $fileHash;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $uploadedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'uploaded_by', referencedColumnName: 'id', nullable: false)]
    private User $uploadedBy;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->uploadedAt = new \DateTimeImmutable();
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

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): static
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;

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

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
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
}
