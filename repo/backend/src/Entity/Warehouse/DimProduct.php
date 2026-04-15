<?php

declare(strict_types=1);

namespace App\Entity\Warehouse;

use App\Repository\Warehouse\DimProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DimProductRepository::class)]
#[ORM\Table(name: 'wh_dim_product')]
#[ORM\Index(columns: ['product_id', 'is_current'], name: 'idx_wh_dim_product_pid_current')]
class DimProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $productKey = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $productId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $productName;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $subcategory = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $effectiveFrom;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $effectiveTo = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isCurrent = true;

    public function getProductKey(): ?int
    {
        return $this->productKey;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function setProductId(int $productId): static
    {
        $this->productId = $productId;

        return $this;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): static
    {
        $this->productName = $productName;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getSubcategory(): ?string
    {
        return $this->subcategory;
    }

    public function setSubcategory(?string $subcategory): static
    {
        $this->subcategory = $subcategory;

        return $this;
    }

    public function getEffectiveFrom(): \DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function setEffectiveFrom(\DateTimeImmutable $effectiveFrom): static
    {
        $this->effectiveFrom = $effectiveFrom;

        return $this;
    }

    public function getEffectiveTo(): ?\DateTimeImmutable
    {
        return $this->effectiveTo;
    }

    public function setEffectiveTo(?\DateTimeImmutable $effectiveTo): static
    {
        $this->effectiveTo = $effectiveTo;

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
