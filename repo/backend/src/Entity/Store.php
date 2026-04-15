<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\StoreType;
use App\Repository\StoreRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: StoreRepository::class)]
#[ORM\Table(name: 'stores')]
#[ORM\Index(columns: ['region_id'], name: 'idx_stores_region_id')]
#[ORM\Index(columns: ['store_type'], name: 'idx_stores_store_type')]
#[ORM\Index(columns: ['is_active'], name: 'idx_stores_is_active')]
class Store
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 20, unique: true)]
    private string $code;

    #[ORM\Column(type: Types::STRING, length: 150)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: StoreType::class)]
    private StoreType $storeType;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'ACTIVE'])]
    private string $status = 'ACTIVE';

    #[ORM\ManyToOne(targetEntity: MdmRegion::class)]
    #[ORM\JoinColumn(name: 'region_id', referencedColumnName: 'id', nullable: false)]
    private MdmRegion $region;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['default' => 'UTC'])]
    private string $timezone = 'UTC';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $addressLine1 = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $addressLine2 = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

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

    public function getStoreType(): StoreType
    {
        return $this->storeType;
    }

    public function setStoreType(StoreType $storeType): static
    {
        $this->storeType = $storeType;

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

    public function getRegion(): MdmRegion
    {
        return $this->region;
    }

    public function setRegion(MdmRegion $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getAddressLine1(): ?string
    {
        return $this->addressLine1;
    }

    public function setAddressLine1(?string $addressLine1): static
    {
        $this->addressLine1 = $addressLine1;

        return $this;
    }

    public function getAddressLine2(): ?string
    {
        return $this->addressLine2;
    }

    public function setAddressLine2(?string $addressLine2): static
    {
        $this->addressLine2 = $addressLine2;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;

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
