<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DataClassificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DataClassificationRepository::class)]
#[ORM\Table(name: 'data_classifications')]
#[ORM\UniqueConstraint(columns: ['entity_type', 'entity_id', 'field_name'])]
class DataClassification
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $entityType;

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $entityId;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $fieldName = null;

    #[ORM\Column(type: Types::STRING, length: 30)]
    private string $classification;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $classifiedBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $classifiedAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->classifiedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getFieldName(): ?string
    {
        return $this->fieldName;
    }

    public function setFieldName(?string $fieldName): static
    {
        $this->fieldName = $fieldName;

        return $this;
    }

    public function getClassification(): string
    {
        return $this->classification;
    }

    public function setClassification(string $classification): static
    {
        $this->classification = $classification;

        return $this;
    }

    public function getClassifiedBy(): User
    {
        return $this->classifiedBy;
    }

    public function setClassifiedBy(User $classifiedBy): static
    {
        $this->classifiedBy = $classifiedBy;

        return $this;
    }

    public function getClassifiedAt(): \DateTimeImmutable
    {
        return $this->classifiedAt;
    }
}
