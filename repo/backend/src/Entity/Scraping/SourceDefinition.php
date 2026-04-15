<?php

declare(strict_types=1);

namespace App\Entity\Scraping;

use App\Repository\Scraping\SourceDefinitionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SourceDefinitionRepository::class)]
#[ORM\Table(name: 'scrape_source_definitions')]
class SourceDefinition
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 512)]
    private string $baseUrl;

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $scrapeType;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $config = [];

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'ACTIVE'])]
    private string $status = 'ACTIVE';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 30])]
    private int $maxRequestsPerMinute = 30;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $pausedUntil = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $pauseReason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSuccessfulScrapeAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl): static
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function getScrapeType(): string
    {
        return $this->scrapeType;
    }

    public function setScrapeType(string $scrapeType): static
    {
        $this->scrapeType = $scrapeType;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        return $this->config;
    }

    /** @param array<string, mixed> $config */
    public function setConfig(array $config): static
    {
        $this->config = $config;

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

    public function getMaxRequestsPerMinute(): int
    {
        return $this->maxRequestsPerMinute;
    }

    public function setMaxRequestsPerMinute(int $maxRequestsPerMinute): static
    {
        $this->maxRequestsPerMinute = $maxRequestsPerMinute;

        return $this;
    }

    public function getPausedUntil(): ?\DateTimeImmutable
    {
        return $this->pausedUntil;
    }

    public function setPausedUntil(?\DateTimeImmutable $pausedUntil): static
    {
        $this->pausedUntil = $pausedUntil;

        return $this;
    }

    public function getPauseReason(): ?string
    {
        return $this->pauseReason;
    }

    public function setPauseReason(?string $pauseReason): static
    {
        $this->pauseReason = $pauseReason;

        return $this;
    }

    public function getLastSuccessfulScrapeAt(): ?\DateTimeImmutable
    {
        return $this->lastSuccessfulScrapeAt;
    }

    public function setLastSuccessfulScrapeAt(?\DateTimeImmutable $lastSuccessfulScrapeAt): static
    {
        $this->lastSuccessfulScrapeAt = $lastSuccessfulScrapeAt;

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
}
