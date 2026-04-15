<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ContentStatus;
use App\Enum\ContentType;
use App\Repository\ContentItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ContentItemRepository::class)]
#[ORM\Table(name: 'content_items')]
#[ORM\Index(columns: ['content_type'], name: 'idx_content_items_type')]
#[ORM\Index(columns: ['status'], name: 'idx_content_items_status')]
#[ORM\Index(columns: ['store_id'], name: 'idx_content_items_store')]
#[ORM\Index(columns: ['region_id'], name: 'idx_content_items_region')]
#[ORM\Index(columns: ['published_at'], name: 'idx_content_items_published_at')]
class ContentItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 30)]
    private string $contentType;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(type: Types::STRING, length: 120)]
    private string $authorName;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $sourceType = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $sourceReference = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $storeId = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $regionId = null;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'DRAFT'])]
    private string $status = 'DRAFT';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $viewCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $replyCount = 0;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ContentVersion> */
    #[ORM\OneToMany(targetEntity: ContentVersion::class, mappedBy: 'contentItem', cascade: ['persist'])]
    #[ORM\OrderBy(['versionNumber' => 'DESC'])]
    private Collection $versions;

    /** @var Collection<int, ContentTag> */
    #[ORM\OneToMany(targetEntity: ContentTag::class, mappedBy: 'contentItem', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $tags;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->versions = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getContentTypeEnum(): ContentType
    {
        return ContentType::from($this->contentType);
    }

    public function setContentType(string $contentType): static
    {
        $this->contentType = $contentType;

        return $this;
    }

    public function setContentTypeEnum(ContentType $contentType): static
    {
        $this->contentType = $contentType->value;

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

    public function getAuthorName(): string
    {
        return $this->authorName;
    }

    public function setAuthorName(string $authorName): static
    {
        $this->authorName = $authorName;

        return $this;
    }

    public function getSourceType(): ?string
    {
        return $this->sourceType;
    }

    public function setSourceType(?string $sourceType): static
    {
        $this->sourceType = $sourceType;

        return $this;
    }

    public function getSourceReference(): ?string
    {
        return $this->sourceReference;
    }

    public function setSourceReference(?string $sourceReference): static
    {
        $this->sourceReference = $sourceReference;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getStoreId(): ?Uuid
    {
        return $this->storeId;
    }

    public function setStoreId(?Uuid $storeId): static
    {
        $this->storeId = $storeId;

        return $this;
    }

    public function getRegionId(): ?Uuid
    {
        return $this->regionId;
    }

    public function setRegionId(?Uuid $regionId): static
    {
        $this->regionId = $regionId;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getStatusEnum(): ContentStatus
    {
        return ContentStatus::from($this->status);
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function setStatusEnum(ContentStatus $status): static
    {
        $this->status = $status->value;

        return $this;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function setViewCount(int $viewCount): static
    {
        $this->viewCount = $viewCount;

        return $this;
    }

    public function getReplyCount(): int
    {
        return $this->replyCount;
    }

    public function setReplyCount(int $replyCount): static
    {
        $this->replyCount = $replyCount;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
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

    /**
     * @return Collection<int, ContentVersion>
     */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    /**
     * @return Collection<int, ContentTag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    /**
     * @return list<string>
     */
    public function getTagValues(): array
    {
        return $this->tags->map(static fn (ContentTag $t) => $t->getTag())->toArray();
    }
}
