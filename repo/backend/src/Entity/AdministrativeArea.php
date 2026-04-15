<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AdministrativeAreaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AdministrativeAreaRepository::class)]
#[ORM\Table(name: 'administrative_areas')]
class AdministrativeArea
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 20, unique: true)]
    private string $code;

    #[ORM\Column(type: Types::STRING, length: 150)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $areaType;

    #[ORM\ManyToOne(targetEntity: MdmRegion::class)]
    #[ORM\JoinColumn(name: 'region_id', referencedColumnName: 'id', nullable: false)]
    private MdmRegion $region;

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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

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

    public function getAreaType(): string
    {
        return $this->areaType;
    }

    public function setAreaType(string $areaType): static
    {
        $this->areaType = $areaType;

        return $this;
    }

    public function getRegion(): MdmRegion
    {
        return $this->region;
    }

    public function setRegion(MdmRegion $region): static
    {
        $this->region = $region;

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
