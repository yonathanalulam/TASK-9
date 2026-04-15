<?php

declare(strict_types=1);

namespace App\Entity\Scraping;

use App\Repository\Scraping\SourceRateLimitRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SourceRateLimitRepository::class)]
#[ORM\Table(name: 'scrape_source_rate_limits')]
#[ORM\UniqueConstraint(name: 'uniq_source_window', columns: ['source_definition_id', 'window_start'])]
class SourceRateLimit
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: SourceDefinition::class)]
    #[ORM\JoinColumn(name: 'source_definition_id', referencedColumnName: 'id', nullable: false)]
    private SourceDefinition $sourceDefinition;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $windowStart;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $requestCount = 0;

    public function __construct()
    {
        $this->id = Uuid::v7();
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

    public function getWindowStart(): \DateTimeImmutable
    {
        return $this->windowStart;
    }

    public function setWindowStart(\DateTimeImmutable $windowStart): static
    {
        $this->windowStart = $windowStart;

        return $this;
    }

    public function getRequestCount(): int
    {
        return $this->requestCount;
    }

    public function setRequestCount(int $requestCount): static
    {
        $this->requestCount = $requestCount;

        return $this;
    }
}
