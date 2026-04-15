<?php

declare(strict_types=1);

namespace App\Entity\Warehouse;

use App\Repository\Warehouse\FactSalesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FactSalesRepository::class)]
#[ORM\Table(name: 'wh_fact_sales')]
#[ORM\Index(columns: ['time_key', 'product_key'], name: 'idx_wh_fact_sales_time_product')]
#[ORM\Index(columns: ['time_key', 'region_key'], name: 'idx_wh_fact_sales_time_region')]
class FactSales
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DimProduct::class)]
    #[ORM\JoinColumn(name: 'product_key', referencedColumnName: 'product_key', nullable: false)]
    private DimProduct $product;

    #[ORM\ManyToOne(targetEntity: DimCustomer::class)]
    #[ORM\JoinColumn(name: 'customer_key', referencedColumnName: 'customer_key', nullable: false)]
    private DimCustomer $customer;

    #[ORM\ManyToOne(targetEntity: DimChannel::class)]
    #[ORM\JoinColumn(name: 'channel_key', referencedColumnName: 'channel_key', nullable: false)]
    private DimChannel $channel;

    #[ORM\ManyToOne(targetEntity: DimRegion::class)]
    #[ORM\JoinColumn(name: 'region_key', referencedColumnName: 'region_key', nullable: false)]
    private DimRegion $region;

    #[ORM\ManyToOne(targetEntity: DimTime::class)]
    #[ORM\JoinColumn(name: 'time_key', referencedColumnName: 'time_key', nullable: false)]
    private DimTime $time;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $grossSales;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $netSales;

    #[ORM\Column(type: Types::INTEGER)]
    private int $quantity;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $orderCount = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): DimProduct
    {
        return $this->product;
    }

    public function setProduct(DimProduct $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getCustomer(): DimCustomer
    {
        return $this->customer;
    }

    public function setCustomer(DimCustomer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getChannel(): DimChannel
    {
        return $this->channel;
    }

    public function setChannel(DimChannel $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function getRegion(): DimRegion
    {
        return $this->region;
    }

    public function setRegion(DimRegion $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function getTime(): DimTime
    {
        return $this->time;
    }

    public function setTime(DimTime $time): static
    {
        $this->time = $time;

        return $this;
    }

    public function getGrossSales(): string
    {
        return $this->grossSales;
    }

    public function setGrossSales(string $grossSales): static
    {
        $this->grossSales = $grossSales;

        return $this;
    }

    public function getNetSales(): string
    {
        return $this->netSales;
    }

    public function setNetSales(string $netSales): static
    {
        $this->netSales = $netSales;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getOrderCount(): int
    {
        return $this->orderCount;
    }

    public function setOrderCount(int $orderCount): static
    {
        $this->orderCount = $orderCount;

        return $this;
    }
}
