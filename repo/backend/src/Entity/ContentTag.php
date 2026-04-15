<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ContentTagRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContentTagRepository::class)]
#[ORM\Table(name: 'content_tags')]
#[ORM\Index(columns: ['tag'], name: 'idx_content_tags_tag')]
class ContentTag
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: ContentItem::class, inversedBy: 'tags')]
    #[ORM\JoinColumn(name: 'content_item_id', nullable: false, onDelete: 'CASCADE')]
    private ContentItem $contentItem;

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $tag;

    public function __construct(ContentItem $contentItem, string $tag)
    {
        $this->contentItem = $contentItem;
        $this->tag = $tag;
    }

    public function getContentItem(): ContentItem
    {
        return $this->contentItem;
    }

    public function getTag(): string
    {
        return $this->tag;
    }
}
