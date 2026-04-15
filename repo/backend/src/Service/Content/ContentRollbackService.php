<?php

declare(strict_types=1);

namespace App\Service\Content;

use App\Entity\ContentItem;
use App\Entity\ContentTag;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\ContentStatus;
use App\Service\Audit\AuditService;
use Doctrine\ORM\EntityManagerInterface;

class ContentRollbackService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContentVersionService $contentVersionService,
        private readonly AuditService $auditService,
    ) {
    }

    public function rollback(
        ContentItem $item,
        string $targetVersionId,
        string $reason,
        User $actor,
    ): ContentItem {
        // Validate: item is NOT ARCHIVED.
        if ($item->getStatusEnum() === ContentStatus::ARCHIVED) {
            throw new \InvalidArgumentException('Cannot rollback archived content.');
        }

        // Validate: target version exists and belongs to this item.
        $targetVersion = $this->contentVersionService->getVersion($targetVersionId);

        if ($targetVersion === null) {
            throw new \InvalidArgumentException('Target version not found.');
        }

        if ($targetVersion->getContentItem()->getId()->toRfc4122() !== $item->getId()->toRfc4122()) {
            throw new \InvalidArgumentException('Target version does not belong to this content item.');
        }

        // Validate: target version createdAt is within 30 calendar days of now.
        $now = new \DateTimeImmutable();
        $daysDiff = (int) $now->diff($targetVersion->getCreatedAt())->days;

        if ($daysDiff > 30) {
            throw new \InvalidArgumentException('Rollback window expired.');
        }

        // Validate: reason is at least 10 characters.
        if (mb_strlen($reason) < 10) {
            throw new \InvalidArgumentException('Rollback reason must be at least 10 characters.');
        }

        $oldSnapshot = $this->snapshotItem($item);

        // Restore title, body, tags from target version to item.
        $item->setTitle($targetVersion->getTitle());
        $item->setBody($targetVersion->getBody());
        $item->setContentType($targetVersion->getContentType());

        // Replace tags.
        foreach ($item->getTags()->toArray() as $existingTag) {
            $this->entityManager->remove($existingTag);
        }
        $item->getTags()->clear();

        foreach ($targetVersion->getTags() as $tagValue) {
            $tag = new ContentTag($item, $tagValue);
            $this->entityManager->persist($tag);
        }

        // Set item status to ROLLED_BACK.
        $item->setStatusEnum(ContentStatus::ROLLED_BACK);
        $item->setUpdatedAt(new \DateTimeImmutable());

        // Create new version with isRollback=true.
        $this->contentVersionService->createVersion(
            $item,
            $actor,
            $reason,
            true,
            $targetVersionId,
        );

        // Record audit.
        $this->auditService->record(
            AuditAction::CONTENT_ROLLED_BACK->value,
            'ContentItem',
            $item->getId()->toRfc4122(),
            $oldSnapshot,
            $this->snapshotItem($item),
            $actor,
        );

        $this->entityManager->flush();

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotItem(ContentItem $item): array
    {
        return [
            'id' => $item->getId()->toRfc4122(),
            'content_type' => $item->getContentType(),
            'title' => $item->getTitle(),
            'body' => mb_substr($item->getBody(), 0, 500),
            'author_name' => $item->getAuthorName(),
            'status' => $item->getStatus(),
            'store_id' => $item->getStoreId()?->toRfc4122(),
            'region_id' => $item->getRegionId()?->toRfc4122(),
            'published_at' => $item->getPublishedAt()?->format('c'),
            'tags' => $item->getTagValues(),
        ];
    }
}
