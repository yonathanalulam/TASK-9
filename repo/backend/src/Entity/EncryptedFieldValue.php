<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EncryptedFieldValueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EncryptedFieldValueRepository::class)]
#[ORM\Table(name: 'encrypted_field_values')]
#[ORM\UniqueConstraint(columns: ['entity_type', 'entity_id', 'field_name'])]
class EncryptedFieldValue
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $entityType;

    #[ORM\Column(type: Types::BINARY, length: 16)]
    private string $entityId;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $fieldName;

    #[ORM\Column(type: Types::BINARY, length: 8192)]
    private string $encryptedValue;

    #[ORM\Column(type: Types::BINARY, length: 16)]
    private string $iv;

    #[ORM\Column(type: Types::BINARY, length: 16)]
    private string $authTag;

    #[ORM\ManyToOne(targetEntity: EncryptionKey::class)]
    #[ORM\JoinColumn(nullable: false)]
    private EncryptionKey $encryptionKey;

    public function __construct()
    {
        $this->id = Uuid::v7();
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

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function setFieldName(string $fieldName): static
    {
        $this->fieldName = $fieldName;

        return $this;
    }

    public function getEncryptedValue(): string
    {
        return $this->encryptedValue;
    }

    public function setEncryptedValue(string $encryptedValue): static
    {
        $this->encryptedValue = $encryptedValue;

        return $this;
    }

    public function getIv(): string
    {
        return $this->iv;
    }

    public function setIv(string $iv): static
    {
        $this->iv = $iv;

        return $this;
    }

    public function getAuthTag(): string
    {
        return $this->authTag;
    }

    public function setAuthTag(string $authTag): static
    {
        $this->authTag = $authTag;

        return $this;
    }

    public function getEncryptionKey(): EncryptionKey
    {
        return $this->encryptionKey;
    }

    public function setEncryptionKey(EncryptionKey $encryptionKey): static
    {
        $this->encryptionKey = $encryptionKey;

        return $this;
    }
}
