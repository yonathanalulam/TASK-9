<?php

declare(strict_types=1);

namespace App\Entity\Scraping;

use App\Repository\Scraping\ScrapeRunRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ScrapeRunRepository::class)]
#[ORM\Table(name: 'scrape_runs')]
class ScrapeRun
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: SourceDefinition::class)]
    #[ORM\JoinColumn(name: 'source_definition_id', referencedColumnName: 'id', nullable: false)]
    private SourceDefinition $sourceDefinition;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'PENDING'])]
    private string $status = 'PENDING';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $itemsFound = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $itemsNew = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $itemsUpdated = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $itemsFailed = 0;

    #[ORM\ManyToOne(targetEntity: ProxyPool::class)]
    #[ORM\JoinColumn(name: 'proxy_pool_id', referencedColumnName: 'id', nullable: true)]
    private ?ProxyPool $proxyPool = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorDetail = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, ScrapeRunItem> */
    #[ORM\OneToMany(targetEntity: ScrapeRunItem::class, mappedBy: 'scrapeRun', cascade: ['persist'])]
    private Collection $items;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSourceDefinition(): SourceDefinition
    {
        return $this->sourceDefinition;
    }

    public function setSourceDefinition(SourceDefinition $sourceDefinition): static
    {
        $this->sourceDefinition = $sourceDefinition;

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

    public function getItemsFound(): int
    {
        return $this->itemsFound;
    }

    public function setItemsFound(int $itemsFound): static
    {
        $this->itemsFound = $itemsFound;

        return $this;
    }

    public function getItemsNew(): int
    {
        return $this->itemsNew;
    }

    public function setItemsNew(int $itemsNew): static
    {
        $this->itemsNew = $itemsNew;

        return $this;
    }

    public function getItemsUpdated(): int
    {
        return $this->itemsUpdated;
    }

    public function setItemsUpdated(int $itemsUpdated): static
    {
        $this->itemsUpdated = $itemsUpdated;

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

    public function getProxyPool(): ?ProxyPool
    {
        return $this->proxyPool;
    }

    public function setProxyPool(?ProxyPool $proxyPool): static
    {
        $this->proxyPool = $proxyPool;

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

    /** @return Collection<int, ScrapeRunItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }
}
