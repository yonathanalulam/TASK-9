<?php

declare(strict_types=1);

namespace App\Entity\Warehouse;

use App\Repository\Warehouse\DimCustomerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DimCustomerRepository::class)]
#[ORM\Table(name: 'wh_dim_customer')]
#[ORM\Index(columns: ['customer_id', 'is_current'], name: 'idx_wh_dim_customer_cid_current')]
class DimCustomer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $customerKey = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $customerId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $customerName;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $customerSegment = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $effectiveFrom;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $effectiveTo = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isCurrent = true;

    public function getCustomerKey(): ?int
    {
        return $this->customerKey;
    }

    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    public function setCustomerId(int $customerId): static
    {
        $this->customerId = $customerId;

        return $this;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): static
    {
        $this->customerName = $customerName;

        return $this;
    }

    public function getCustomerSegment(): ?string
    {
        return $this->customerSegment;
    }

    public function setCustomerSegment(?string $customerSegment): static
    {
        $this->customerSegment = $customerSegment;

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
