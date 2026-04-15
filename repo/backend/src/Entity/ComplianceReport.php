<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ComplianceReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ComplianceReportRepository::class)]
#[ORM\Table(name: 'compliance_reports')]
class ComplianceReport
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $reportType;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $generatedBy;

    #[ORM\Column(type: Types::JSON)]
    private array $parameters;

    #[ORM\Column(type: Types::STRING, length: 512)]
    private string $filePath;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $tamperHashSha256;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $previousReportId = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $previousReportHash = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $generatedAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->generatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getReportType(): string
    {
        return $this->reportType;
    }

    public function setReportType(string $reportType): static
    {
        $this->reportType = $reportType;

        return $this;
    }

    public function getGeneratedBy(): User
    {
        return $this->generatedBy;
    }

    public function setGeneratedBy(User $generatedBy): static
    {
        $this->generatedBy = $generatedBy;

        return $this;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters): static
    {
        $this->parameters = $parameters;

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

    public function getTamperHashSha256(): string
    {
        return $this->tamperHashSha256;
    }

    public function setTamperHashSha256(string $tamperHashSha256): static
    {
        $this->tamperHashSha256 = $tamperHashSha256;

        return $this;
    }

    public function getPreviousReportId(): ?Uuid
    {
        return $this->previousReportId;
    }

    public function setPreviousReportId(?Uuid $previousReportId): static
    {
        $this->previousReportId = $previousReportId;

        return $this;
    }

    public function getPreviousReportHash(): ?string
    {
        return $this->previousReportHash;
    }

    public function setPreviousReportHash(?string $previousReportHash): static
    {
        $this->previousReportHash = $previousReportHash;

        return $this;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }
}
