<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ZoneProductRuleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ZoneProductRuleRepository::class)]
#[ORM\Table(name: 'zone_product_rules')]
class ZoneProductRule
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: DeliveryZone::class)]
    #[ORM\JoinColumn(name: 'zone_id', referencedColumnName: 'id', nullable: false)]
    private DeliveryZone $zone;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $ruleType;

    #[ORM\Column(type: Types::JSON)]
    private array $ruleConfig = [];

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

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

    public function getZone(): DeliveryZone
    {
        return $this->zone;
    }

    public function setZone(DeliveryZone $zone): static
    {
        $this->zone = $zone;

        return $this;
    }

    public function getRuleType(): string
    {
        return $this->ruleType;
    }

    public function setRuleType(string $ruleType): static
    {
        $this->ruleType = $ruleType;

        return $this;
    }

    public function getRuleConfig(): array
    {
        return $this->ruleConfig;
    }

    public function setRuleConfig(array $ruleConfig): static
    {
        $this->ruleConfig = $ruleConfig;

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
}
