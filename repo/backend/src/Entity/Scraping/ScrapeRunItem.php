<?php

declare(strict_types=1);

namespace App\Entity\Scraping;

use App\Repository\Scraping\ScrapeRunItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ScrapeRunItemRepository::class)]
#[ORM\Table(name: 'scrape_run_items')]
class ScrapeRunItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: ScrapeRun::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'scrape_run_id', referencedColumnName: 'id', nullable: false)]
    private ScrapeRun $scrapeRun;

    #[ORM\Column(type: Types::STRING, length: 2048)]
    private string $sourceUrl;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rawContent = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $extractedData = null;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'SCRAPED'])]
    private string $status = 'SCRAPED';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorDetail = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $contentItemId = null;

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

    public function getScrapeRun(): ScrapeRun
    {
        return $this->scrapeRun;
    }

    public function setScrapeRun(ScrapeRun $scrapeRun): static
    {
        $this->scrapeRun = $scrapeRun;

        return $this;
    }

    public function getSourceUrl(): string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(string $sourceUrl): static
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function getRawContent(): ?string
    {
        return $this->rawContent;
    }

    public function setRawContent(?string $rawContent): static
    {
        $this->rawContent = $rawContent;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getExtractedData(): ?array
    {
        return $this->extractedData;
    }

    /** @param array<string, mixed>|null $extractedData */
    public function setExtractedData(?array $extractedData): static
    {
        $this->extractedData = $extractedData;

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

    public function getErrorDetail(): ?string
    {
        return $this->errorDetail;
    }

    public function setErrorDetail(?string $errorDetail): static
    {
        $this->errorDetail = $errorDetail;

        return $this;
    }

    public function getContentItemId(): ?string
    {
        return $this->contentItemId;
    }

    public function setContentItemId(?string $contentItemId): static
    {
        $this->contentItemId = $contentItemId;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
