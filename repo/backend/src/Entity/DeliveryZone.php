<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DeliveryZoneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DeliveryZoneRepository::class)]
#[ORM\Table(name: 'delivery_zones')]
#[ORM\Index(columns: ['store_id'], name: 'idx_delivery_zones_store_id')]
#[ORM\Index(columns: ['is_active'], name: 'idx_delivery_zones_is_active')]
class DeliveryZone
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Store::class)]
    #[ORM\JoinColumn(name: 'store_id', referencedColumnName: 'id', nullable: false)]
    private Store $store;

    #[ORM\Column(type: Types::STRING, length: 120)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'ACTIVE'])]
    private string $status = 'ACTIVE';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '25.00'])]
    private string $minOrderThreshold = '25.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '3.99'])]
    private string $deliveryFee = '3.99';

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $version = 1;

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

    public function getStore(): Store
    {
        return $this->store;
    }

    public function setStore(Store $store): static
    {
        $this->store = $store;

        return $this;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getMinOrderThreshold(): string
    {
        return $this->minOrderThreshold;
    }

    public function setMinOrderThreshold(string $minOrderThreshold): static
    {
        $this->minOrderThreshold = $minOrderThreshold;

        return $this;
    }

    public function getDeliveryFee(): string
    {
        return $this->deliveryFee;
    }

    public function setDeliveryFee(string $deliveryFee): static
    {
        $this->deliveryFee = $deliveryFee;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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

    public function getVersion(): int
    {
        return $this->version;
    }
}
