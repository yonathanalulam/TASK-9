<?php

declare(strict_types=1);

namespace App\Service\Content;

use App\Entity\ContentItem;
use App\Entity\ContentTag;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\ContentStatus;
use App\Enum\ContentType;
use App\Repository\ContentItemRepository;
use App\Service\Audit\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class ContentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContentItemRepository $contentItemRepository,
        private readonly ContentVersionService $contentVersionService,
        private readonly AuditService $auditService,
    ) {
    }

    public function create(array $data, User $actor): ContentItem
    {
        $title = $data['title'] ?? '';
        $body = $data['body'] ?? '';
        $authorName = $data['author_name'] ?? '';
        $contentTypeValue = $data['content_type'] ?? '';
        $tags = $data['tags'] ?? [];

        $this->validateTitle($title);
        $this->validateBody($body);
        $this->validateAuthorName($authorName);
        $contentTypeEnum = $this->validateContentType($contentTypeValue);
        $this->validateTags($tags);

        $item = new ContentItem();
        $item->setTitle($title);
        $item->setBody($body);
        $item->setAuthorName($authorName);
        $item->setContentTypeEnum($contentTypeEnum);
        $item->setStatusEnum(ContentStatus::DRAFT);

        if (array_key_exists('source_type', $data) && is_string($data['source_type'])) {
            $item->setSourceType($data['source_type']);
        }
        if (array_key_exists('source_reference', $data) && is_string($data['source_reference'])) {
            $item->setSourceReference($data['source_reference']);
        }
        if (array_key_exists('store_id', $data) && is_string($data['store_id']) && $data['store_id'] !== '') {
            $item->setStoreId(Uuid::fromString($data['store_id']));
        }
        if (array_key_exists('region_id', $data) && is_string($data['region_id']) && $data['region_id'] !== '') {
            $item->setRegionId(Uuid::fromString($data['region_id']));
        }

        $this->entityManager->persist($item);

        // Create tags.
        foreach ($tags as $tagValue) {
            $tag = new ContentTag($item, $tagValue);
            $this->entityManager->persist($tag);
        }

        // Create first version (v1).
        $this->contentVersionService->createVersion(
            $item,
            $actor,
            'Initial creation',
        );

        $this->auditService->record(
            AuditAction::CONTENT_CREATED->value,
            'ContentItem',
            $item->getId()->toRfc4122(),
            null,
            $this->snapshotItem($item),
            $actor,
        );

        $this->entityManager->flush();

        return $item;
    }

    public function update(ContentItem $item, array $data, User $actor): ContentItem
    {
        $oldSnapshot = $this->snapshotItem($item);

        if (array_key_exists('title', $data)) {
            $this->validateTitle($data['title']);
            $item->setTitle($data['title']);
        }
        if (array_key_exists('body', $data)) {
            $this->validateBody($data['body']);
            $item->setBody($data['body']);
        }
        if (array_key_exists('author_name', $data)) {
            $this->validateAuthorName($data['author_name']);
            $item->setAuthorName($data['author_name']);
        }
        if (array_key_exists('content_type', $data)) {
            $contentTypeEnum = $this->validateContentType($data['content_type']);
            $item->setContentTypeEnum($contentTypeEnum);
        }
        if (array_key_exists('source_type', $data)) {
            $item->setSourceType($data['source_type']);
        }
        if (array_key_exists('source_reference', $data)) {
            $item->setSourceReference($data['source_reference']);
        }
        if (array_key_exists('store_id', $data)) {
            $storeId = $data['store_id'];
            $item->setStoreId($storeId !== null && $storeId !== '' ? Uuid::fromString($storeId) : null);
        }
        if (array_key_exists('region_id', $data)) {
            $regionId = $data['region_id'];
            $item->setRegionId($regionId !== null && $regionId !== '' ? Uuid::fromString($regionId) : null);
        }

        // Update tags if provided.
        if (array_key_exists('tags', $data)) {
            $tags = $data['tags'] ?? [];
            $this->validateTags($tags);
            $this->replaceTags($item, $tags);
        }

        // Transition status if currently PUBLISHED.
        if ($item->getStatusEnum() === ContentStatus::PUBLISHED) {
            $item->setStatusEnum(ContentStatus::UPDATED);
        }

        $item->setUpdatedAt(new \DateTimeImmutable());

        $changeReason = $data['change_reason'] ?? null;

        $this->contentVersionService->createVersion(
            $item,
            $actor,
            $changeReason,
        );

        $this->auditService->record(
            AuditAction::CONTENT_UPDATED->value,
            'ContentItem',
            $item->getId()->toRfc4122(),
            $oldSnapshot,
            $this->snapshotItem($item),
            $actor,
        );

        $this->entityManager->flush();

        return $item;
    }

    public function publish(ContentItem $item, User $actor): ContentItem
    {
        $oldSnapshot = $this->snapshotItem($item);

        $item->setStatusEnum(ContentStatus::PUBLISHED);
        $item->setPublishedAt(new \DateTimeImmutable());
        $item->setUpdatedAt(new \DateTimeImmutable());

        $this->contentVersionService->createVersion(
            $item,
            $actor,
            'Published',
        );

        $this->auditService->record(
            AuditAction::CONTENT_PUBLISHED->value,
            'ContentItem',
            $item->getId()->toRfc4122(),
            $oldSnapshot,
            $this->snapshotItem($item),
            $actor,
        );

        $this->entityManager->flush();

        return $item;
    }

    public function archive(ContentItem $item, User $actor): ContentItem
    {
        $oldSnapshot = $this->snapshotItem($item);

        $item->setStatusEnum(ContentStatus::ARCHIVED);
        $item->setUpdatedAt(new \DateTimeImmutable());

        $this->contentVersionService->createVersion(
            $item,
            $actor,
            'Archived',
        );

        $this->auditService->record(
            AuditAction::CONTENT_ARCHIVED->value,
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
     * @param string[]|null $accessibleStoreIds  null = unrestricted (GLOBAL scope)
     * @param string[]|null $accessibleRegionIds null = unrestricted (GLOBAL scope)
     * @return array{items: list<ContentItem>, total: int}
     */
    public function list(
        int $page,
        int $perPage,
        ?string $contentType = null,
        ?string $storeId = null,
        ?string $regionId = null,
        ?string $status = null,
        ?array $accessibleStoreIds = null,
        ?array $accessibleRegionIds = null,
    ): array {
        $qb = $this->contentItemRepository->createQueryBuilder('c');

        if ($contentType !== null) {
            $qb->andWhere('c.contentType = :contentType')
                ->setParameter('contentType', $contentType);
        }
        if ($storeId !== null) {
            $qb->andWhere('c.storeId = :storeId')
                ->setParameter('storeId', $storeId);
        }
        if ($regionId !== null) {
            $qb->andWhere('c.regionId = :regionId')
                ->setParameter('regionId', $regionId);
        }
        if ($status !== null) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        // Scope filtering: restrict content to authorized stores AND region-only content.
        if ($accessibleStoreIds !== null) {
            if (\count($accessibleStoreIds) === 0 && ($accessibleRegionIds === null || \count($accessibleRegionIds) === 0)) {
                return ['items' => [], 'total' => 0];
            }

            // Convert RFC4122 strings to 16-byte binary for BINARY(16) column comparison.
            $storeBinIds = array_map(
                static fn (string $id) => \Symfony\Component\Uid\Uuid::fromString($id)->toBinary(),
                $accessibleStoreIds,
            );

            if ($accessibleRegionIds !== null && \count($accessibleRegionIds) > 0) {
                $regionBinIds = array_map(
                    static fn (string $id) => \Symfony\Component\Uid\Uuid::fromString($id)->toBinary(),
                    $accessibleRegionIds,
                );
                // Include store-scoped content OR region-only content (no store_id, matching region).
                $qb->andWhere(
                    $qb->expr()->orX(
                        $qb->expr()->in('c.storeId', ':accessibleStoreIds'),
                        $qb->expr()->andX(
                            $qb->expr()->isNull('c.storeId'),
                            $qb->expr()->in('c.regionId', ':accessibleRegionIds'),
                        ),
                    ),
                );
                $qb->setParameter('accessibleStoreIds', $storeBinIds);
                $qb->setParameter('accessibleRegionIds', $regionBinIds);
            } else {
                $qb->andWhere('c.storeId IN (:accessibleStoreIds)')
                    ->setParameter('accessibleStoreIds', $storeBinIds);
            }
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->orderBy('c.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function findById(string $id): ?ContentItem
    {
        return $this->contentItemRepository->find($id);
    }

    private function validateTitle(mixed $title): void
    {
        if (!is_string($title) || mb_strlen($title) < 1 || mb_strlen($title) > 255) {
            throw new \InvalidArgumentException('Title must be between 1 and 255 characters.');
        }
    }

    private function validateBody(mixed $body): void
    {
        if (!is_string($body) || mb_strlen($body) < 1 || mb_strlen($body) > 100000) {
            throw new \InvalidArgumentException('Body must be between 1 and 100000 characters.');
        }
    }

    private function validateAuthorName(mixed $authorName): void
    {
        if (!is_string($authorName) || mb_strlen($authorName) < 1 || mb_strlen($authorName) > 120) {
            throw new \InvalidArgumentException('Author name must be between 1 and 120 characters.');
        }
    }

    private function validateContentType(mixed $contentType): ContentType
    {
        $enum = ContentType::tryFrom((string) $contentType);

        if ($enum === null) {
            $allowed = implode(', ', array_map(static fn (ContentType $t) => $t->value, ContentType::cases()));
            throw new \InvalidArgumentException(sprintf('Content type must be one of: %s.', $allowed));
        }

        return $enum;
    }

    private function validateTags(mixed $tags): void
    {
        if (!is_array($tags)) {
            throw new \InvalidArgumentException('Tags must be an array.');
        }
        if (count($tags) > 20) {
            throw new \InvalidArgumentException('Maximum 20 tags allowed.');
        }
        foreach ($tags as $tag) {
            if (!is_string($tag) || mb_strlen($tag) < 1 || mb_strlen($tag) > 50) {
                throw new \InvalidArgumentException('Each tag must be between 1 and 50 characters.');
            }
        }
    }

    /**
     * @param list<string> $newTags
     */
    private function replaceTags(ContentItem $item, array $newTags): void
    {
        // Remove existing tags.
        foreach ($item->getTags()->toArray() as $existingTag) {
            $this->entityManager->remove($existingTag);
        }
        $item->getTags()->clear();

        // Add new tags.
        foreach ($newTags as $tagValue) {
            $tag = new ContentTag($item, $tagValue);
            $this->entityManager->persist($tag);
        }
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
