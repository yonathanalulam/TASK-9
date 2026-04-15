<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ZoneMappingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ZoneMappingRepository::class)]
#[ORM\Table(name: 'zone_mappings')]
class ZoneMapping
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: DeliveryZone::class)]
    #[ORM\JoinColumn(name: 'zone_id', referencedColumnName: 'id', nullable: false)]
    private DeliveryZone $zone;

    #[ORM\Column(type: Types::STRING, length: 30)]
    private string $mappingType;

    #[ORM\Column(name: 'mapped_entity_id', type: Types::BINARY, length: 16)]
    private string $mappedEntityId;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $precedence = 0;

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

    public function getZone(): DeliveryZone
    {
        return $this->zone;
    }

    public function setZone(DeliveryZone $zone): static
    {
        $this->zone = $zone;

        return $this;
    }

    public function getMappingType(): string
    {
        return $this->mappingType;
    }

    public function setMappingType(string $mappingType): static
    {
        $this->mappingType = $mappingType;

        return $this;
    }

    public function getMappedEntityId(): string
    {
        return $this->mappedEntityId;
    }

    public function setMappedEntityId(string $mappedEntityId): static
    {
        $this->mappedEntityId = $mappedEntityId;

        return $this;
    }

    public function getPrecedence(): int
    {
        return $this->precedence;
    }

    public function setPrecedence(int $precedence): static
    {
        $this->precedence = $precedence;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
