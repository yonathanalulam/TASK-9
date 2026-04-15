<?php

declare(strict_types=1);

namespace App\Entity\Warehouse;

use App\Repository\Warehouse\DimRegionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DimRegionRepository::class)]
#[ORM\Table(name: 'wh_dim_region')]
class DimRegion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $regionKey = null;

    #[ORM\Column(type: Types::STRING, length: 5, nullable: true)]
    private ?string $regionCode = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $regionName;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $regionLevel;

    #[ORM\ManyToOne(targetEntity: DimRegion::class)]
    #[ORM\JoinColumn(name: 'parent_region_key', referencedColumnName: 'region_key', nullable: true)]
    private ?DimRegion $parentRegion = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isCurrent = true;

    public function getRegionKey(): ?int
    {
        return $this->regionKey;
    }

    public function getRegionCode(): ?string
    {
        return $this->regionCode;
    }

    public function setRegionCode(?string $regionCode): static
    {
        $this->regionCode = $regionCode;

        return $this;
    }

    public function getRegionName(): string
    {
        return $this->regionName;
    }

    public function setRegionName(string $regionName): static
    {
        $this->regionName = $regionName;

        return $this;
    }

    public function getRegionLevel(): int
    {
        return $this->regionLevel;
    }

    public function setRegionLevel(int $regionLevel): static
    {
        $this->regionLevel = $regionLevel;

        return $this;
    }

    public function getParentRegion(): ?DimRegion
    {
        return $this->parentRegion;
    }

    public function setParentRegion(?DimRegion $parentRegion): static
    {
        $this->parentRegion = $parentRegion;

        return $this;
    }

    public function isCurrent(): bool
    {
        return $this->isCurrent;
    }

    public function setIsCurrent(bool $isCurrent): static
    {
        $this->isCurrent = $isCurrent;

        return $this;
    }
}
