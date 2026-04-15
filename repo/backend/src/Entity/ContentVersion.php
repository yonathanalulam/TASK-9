<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ContentVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ContentVersionRepository::class)]
#[ORM\Table(name: 'content_versions')]
#[ORM\UniqueConstraint(name: 'uniq_content_version', columns: ['content_item_id', 'version_number'])]
#[ORM\Index(columns: ['created_at'], name: 'idx_content_versions_created_at')]
class ContentVersion
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: ContentItem::class, inversedBy: 'versions')]
    #[ORM\JoinColumn(name: 'content_item_id', nullable: false, onDelete: 'CASCADE')]
    private ContentItem $contentItem;

    #[ORM\Column(type: Types::INTEGER)]
    private int $versionNumber;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(type: Types::JSON)]
    private array $tags = [];

    #[ORM\Column(type: Types::STRING, length: 30)]
    private string $contentType;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $statusAtCreation;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $changeReason = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isRollback = false;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $rolledBackToVersionId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', nullable: false)]
    private User $createdBy;

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

    public function getContentItem(): ContentItem
    {
        return $this->contentItem;
    }

    public function setContentItem(ContentItem $contentItem): static
    {
        $this->contentItem = $contentItem;

        return $this;
    }

    public function getVersionNumber(): int
    {
        return $this->versionNumber;
    }

    public function setVersionNumber(int $versionNumber): static
    {
        $this->versionNumber = $versionNumber;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param list<string> $tags
     */
    public function setTags(array $tags): static
    {
        $this->tags = $tags;

        return $this;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function setContentType(string $contentType): static
    {
        $this->contentType = $contentType;

        return $this;
    }

    public function getStatusAtCreation(): string
    {
        return $this->statusAtCreation;
    }

    public function setStatusAtCreation(string $statusAtCreation): static
    {
        $this->statusAtCreation = $statusAtCreation;

        return $this;
    }

    public function getChangeReason(): ?string
    {
        return $this->changeReason;
    }

    public function setChangeReason(?string $changeReason): static
    {
        $this->changeReason = $changeReason;

        return $this;
    }

    public function isRollback(): bool
    {
        return $this->isRollback;
    }

    public function setIsRollback(bool $isRollback): static
    {
        $this->isRollback = $isRollback;

        return $this;
    }

    public function getRolledBackToVersionId(): ?Uuid
    {
        return $this->rolledBackToVersionId;
    }

    public function setRolledBackToVersionId(?Uuid $rolledBackToVersionId): static
    {
        $this->rolledBackToVersionId = $rolledBackToVersionId;

        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
