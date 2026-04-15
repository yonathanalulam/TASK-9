<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FieldAccessPolicyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FieldAccessPolicyRepository::class)]
#[ORM\Table(name: 'field_access_policies')]
#[ORM\UniqueConstraint(columns: ['role_id', 'entity_type', 'field_name'])]
class FieldAccessPolicy
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Role $role;

    #[ORM\Column(type: Types::STRING, length: 80)]
    private string $entityType;

    #[ORM\Column(type: Types::STRING, length: 80)]
    private string $fieldName;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $canRead = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $canWrite = false;

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

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): static
    {
        $this->role = $role;

        return $this;
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

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function setFieldName(string $fieldName): static
    {
        $this->fieldName = $fieldName;

        return $this;
    }

    public function canRead(): bool
    {
        return $this->canRead;
    }

    public function setCanRead(bool $canRead): static
    {
        $this->canRead = $canRead;

        return $this;
    }

    public function canWrite(): bool
    {
        return $this->canWrite;
    }

    public function setCanWrite(bool $canWrite): static
    {
        $this->canWrite = $canWrite;

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
