<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ContentFingerprintRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ContentFingerprintRepository::class)]
#[ORM\Table(name: 'content_fingerprints')]
#[ORM\UniqueConstraint(columns: ['fingerprint', 'content_item_id'])]
#[ORM\Index(columns: ['fingerprint'], name: 'idx_content_fingerprints_fp')]
class ContentFingerprint
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $fingerprint;

    #[ORM\Column(type: 'uuid')]
    private Uuid $contentItemId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $sourceImportItemId = null;

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

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function setFingerprint(string $fingerprint): static
    {
        $this->fingerprint = $fingerprint;

        return $this;
    }

    public function getContentItemId(): Uuid
    {
        return $this->contentItemId;
    }

    public function setContentItemId(Uuid $contentItemId): static
    {
        $this->contentItemId = $contentItemId;

        return $this;
    }

    public function getSourceImportItemId(): ?Uuid
    {
        return $this->sourceImportItemId;
    }

    public function setSourceImportItemId(?Uuid $sourceImportItemId): static
    {
        $this->sourceImportItemId = $sourceImportItemId;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
