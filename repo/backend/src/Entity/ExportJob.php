<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ExportJobRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ExportJobRepository::class)]
#[ORM\Table(name: 'export_jobs')]
#[ORM\Index(columns: ['status'], name: 'idx_export_jobs_status')]
#[ORM\Index(columns: ['expires_at'], name: 'idx_export_jobs_expires_at')]
class ExportJob
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $dataset;

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $format;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'REQUESTED'])]
    private string $status = 'REQUESTED';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $requestedBy;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $authorizedBy = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $filters = null;

    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $fileName = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $watermarkText = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $tamperHashSha256 = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $authorizedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->requestedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getDataset(): string
    {
        return $this->dataset;
    }

    public function setDataset(string $dataset): static
    {
        $this->dataset = $dataset;

        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setFormat(string $format): static
    {
        $this->format = $format;

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

    public function getRequestedBy(): User
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(User $requestedBy): static
    {
        $this->requestedBy = $requestedBy;

        return $this;
    }

    public function getAuthorizedBy(): ?User
    {
        return $this->authorizedBy;
    }

    public function setAuthorizedBy(?User $authorizedBy): static
    {
        $this->authorizedBy = $authorizedBy;

        return $this;
    }

    public function getFilters(): ?array
    {
        return $this->filters;
    }

    public function setFilters(?array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;

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

    public function getWatermarkText(): ?string
    {
        return $this->watermarkText;
    }

    public function setWatermarkText(?string $watermarkText): static
    {
        $this->watermarkText = $watermarkText;

        return $this;
    }

    public function getTamperHashSha256(): ?string
    {
        return $this->tamperHashSha256;
    }

    public function setTamperHashSha256(?string $tamperHashSha256): static
    {
        $this->tamperHashSha256 = $tamperHashSha256;

        return $this;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getAuthorizedAt(): ?\DateTimeImmutable
    {
        return $this->authorizedAt;
    }

    public function setAuthorizedAt(?\DateTimeImmutable $authorizedAt): static
    {
        $this->authorizedAt = $authorizedAt;

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

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }
}
