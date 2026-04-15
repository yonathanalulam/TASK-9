<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EncryptionKeyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EncryptionKeyRepository::class)]
#[ORM\Table(name: 'encryption_keys')]
#[ORM\Index(columns: ['status'], name: 'idx_encryption_keys_status')]
class EncryptionKey
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    private string $keyAlias;

    #[ORM\Column(type: Types::BINARY, length: 512)]
    private string $encryptedKeyMaterial;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['default' => 'AES-256-GCM'])]
    private string $algorithm = 'AES-256-GCM';

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'ACTIVE'])]
    private string $status = 'ACTIVE';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $rotatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getKeyAlias(): string
    {
        return $this->keyAlias;
    }

    public function setKeyAlias(string $keyAlias): static
    {
        $this->keyAlias = $keyAlias;

        return $this;
    }

    public function getEncryptedKeyMaterial(): string
    {
        return $this->encryptedKeyMaterial;
    }

    public function setEncryptedKeyMaterial(string $encryptedKeyMaterial): static
    {
        $this->encryptedKeyMaterial = $encryptedKeyMaterial;

        return $this;
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function setAlgorithm(string $algorithm): static
    {
        $this->algorithm = $algorithm;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRotatedAt(): ?\DateTimeImmutable
    {
        return $this->rotatedAt;
    }

    public function setRotatedAt(?\DateTimeImmutable $rotatedAt): static
    {
        $this->rotatedAt = $rotatedAt;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }
}
