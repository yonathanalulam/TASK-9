<?php

declare(strict_types=1);

namespace App\Entity\Scraping;

use App\Repository\Scraping\SourceHealthEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SourceHealthEventRepository::class)]
#[ORM\Table(name: 'scrape_source_health_events')]
#[ORM\Index(columns: ['source_definition_id', 'created_at'], name: 'idx_health_events_source_date')]
class SourceHealthEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: SourceDefinition::class)]
    #[ORM\JoinColumn(name: 'source_definition_id', referencedColumnName: 'id', nullable: false)]
    private SourceDefinition $sourceDefinition;

    #[ORM\Column(type: Types::STRING, length: 30)]
    private string $eventType;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $detail = null;

    #[ORM\ManyToOne(targetEntity: ProxyPool::class)]
    #[ORM\JoinColumn(name: 'proxy_pool_id', referencedColumnName: 'id', nullable: true)]
    private ?ProxyPool $proxyPool = null;

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

    public function getSourceDefinition(): SourceDefinition
    {
        return $this->sourceDefinition;
    }

    public function setSourceDefinition(SourceDefinition $sourceDefinition): static
    {
        $this->sourceDefinition = $sourceDefinition;

        return $this;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function setDetail(?string $detail): static
    {
        $this->detail = $detail;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
