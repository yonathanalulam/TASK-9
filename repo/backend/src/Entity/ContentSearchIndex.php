<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ContentSearchIndexRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContentSearchIndexRepository::class)]
#[ORM\Table(name: 'content_search_index')]
#[ORM\Index(columns: ['content_item_id'], name: 'idx_search_index_content_item')]
#[ORM\Index(columns: ['content_type'], name: 'idx_search_index_content_type')]
#[ORM\Index(columns: ['status'], name: 'idx_search_index_status')]
#[ORM\Index(columns: ['store_id'], name: 'idx_search_index_store')]
#[ORM\Index(columns: ['region_id'], name: 'idx_search_index_region')]
#[ORM\Index(columns: ['published_at'], name: 'idx_search_index_published_at')]
class ContentSearchIndex
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $contentItemId;

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $contentVersionId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(type: Types::STRING, length: 1100)]
    private string $tagsText;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $authorName;

    #[ORM\Column(type: Types::TEXT)]
    private string $bodyText;

    #[ORM\Column(type: Types::STRING, length: 30)]
    private string $contentType;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $storeId = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $regionId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $indexedAt;

    public function __construct()
    {
        $this->indexedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContentItemId(): string
    {
        return $this->contentItemId;
    }

    public function setContentItemId(string $contentItemId): static
    {
        $this->contentItemId = $contentItemId;

        return $this;
    }

    public function getContentVersionId(): string
    {
        return $this->contentVersionId;
    }

    public function setContentVersionId(string $contentVersionId): static
    {
        $this->contentVersionId = $contentVersionId;

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

    public function getTagsText(): string
    {
        return $this->tagsText;
    }

    public function setTagsText(string $tagsText): static
    {
        $this->tagsText = $tagsText;

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

    public function getBodyText(): string
    {
        return $this->bodyText;
    }

    public function setBodyText(string $bodyText): static
    {
        $this->bodyText = $bodyText;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStoreId(): ?string
    {
        return $this->storeId;
    }

    public function setStoreId(?string $storeId): static
    {
        $this->storeId = $storeId;

        return $this;
    }

    public function getRegionId(): ?string
    {
        return $this->regionId;
    }

    public function setRegionId(?string $regionId): static
    {
        $this->regionId = $regionId;

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

    public function getIndexedAt(): \DateTimeImmutable
    {
        return $this->indexedAt;
    }

    public function setIndexedAt(\DateTimeImmutable $indexedAt): static
    {
        $this->indexedAt = $indexedAt;

        return $this;
    }
}
