<?php

declare(strict_types=1);

namespace App\Service\Search;

use App\Entity\ContentItem;
use App\Entity\ContentVersion;
use App\Entity\SearchIndexJob;
use App\Repository\SearchIndexJobRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class SearchIndexService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly SearchIndexJobRepository $searchIndexJobRepository,
    ) {
    }

    /**
     * Upsert a single content item into the search index.
     */
    public function indexContent(ContentItem $item): void
    {
        // Get the latest version for this item.
        $latestVersion = $this->entityManager->createQueryBuilder()
            ->select('cv')
            ->from(ContentVersion::class, 'cv')
            ->where('cv.contentItem = :item')
            ->setParameter('item', $item)
            ->orderBy('cv.versionNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $contentVersionId = $latestVersion !== null
            ? $latestVersion->getId()->toRfc4122()
            : $item->getId()->toRfc4122();

        $tags = $latestVersion !== null ? $latestVersion->getTags() : $item->getTagValues();
        $tagsText = implode(' ', $tags);

        $this->connection->executeStatement(
            <<<'SQL'
                REPLACE INTO content_search_index
                    (content_item_id, content_version_id, title, tags_text, author_name, body_text,
                     content_type, status, store_id, region_id, published_at, indexed_at)
                VALUES
                    (:contentItemId, :contentVersionId, :title, :tagsText, :authorName, :bodyText,
                     :contentType, :status, :storeId, :regionId, :publishedAt, :indexedAt)
                SQL,
            [
                'contentItemId' => $item->getId()->toRfc4122(),
                'contentVersionId' => $contentVersionId,
                'title' => $item->getTitle(),
                'tagsText' => $tagsText,
                'authorName' => $item->getAuthorName(),
                'bodyText' => $item->getBody(),
                'contentType' => $item->getContentType(),
                'status' => $item->getStatus(),
                'storeId' => $item->getStoreId()?->toRfc4122(),
                'regionId' => $item->getRegionId()?->toRfc4122(),
                'publishedAt' => $item->getPublishedAt()?->format('Y-m-d H:i:s'),
                'indexedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
        );
    }

    /**
     * Run incremental indexing: find versions newer than the last indexed version and index them.
     */
    public function runIncrementalIndex(): SearchIndexJob
    {
        $job = new SearchIndexJob();
        $job->setStatus('RUNNING');
        $job->setStartedAt(new \DateTimeImmutable());

        $this->entityManager->persist($job);
        $this->entityManager->flush();

        // Find the last successful or partial job to get the last indexed version.
        $lastJob = $this->searchIndexJobRepository->createQueryBuilder('j')
            ->where('j.status IN (:statuses)')
            ->setParameter('statuses', ['SUCCEEDED', 'PARTIAL_SUCCESS'])
            ->orderBy('j.completedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $lastVersionId = $lastJob?->getLastIndexedVersionId();

        // Build query for versions newer than the last indexed version.
        $qb = $this->entityManager->createQueryBuilder()
            ->select('cv')
            ->from(ContentVersion::class, 'cv')
            ->join('cv.contentItem', 'ci')
            ->orderBy('cv.createdAt', 'ASC');

        if ($lastVersionId !== null) {
            // Find the createdAt of the last indexed version.
            $lastVersion = $this->entityManager->find(ContentVersion::class, $lastVersionId);

            if ($lastVersion !== null) {
                $qb->where('cv.createdAt > :lastCreatedAt')
                    ->setParameter('lastCreatedAt', $lastVersion->getCreatedAt());
            }
        }

        /** @var ContentVersion[] $versions */
        $versions = $qb->getQuery()->getResult();

        $processedCount = 0;
        $failedCount = 0;
        $lastIndexedVersionId = null;
        $errors = [];

        // Track unique content items to index each only once (latest version).
        $itemsToIndex = [];
        foreach ($versions as $version) {
            $itemId = $version->getContentItem()->getId()->toRfc4122();
            $itemsToIndex[$itemId] = $version->getContentItem();
            $lastIndexedVersionId = $version->getId();
        }

        foreach ($itemsToIndex as $item) {
            try {
                $this->indexContent($item);
                $processedCount++;
            } catch (\Throwable $e) {
                $failedCount++;
                $errors[] = sprintf(
                    'Item %s: %s',
                    $item->getId()->toRfc4122(),
                    $e->getMessage(),
                );
            }
        }

        // Determine final status.
        if ($failedCount === 0) {
            $job->setStatus('SUCCEEDED');
        } elseif ($processedCount > 0) {
            $job->setStatus('PARTIAL_SUCCESS');
        } else {
            $job->setStatus('FAILED');
        }

        $job->setItemsProcessed($processedCount);
        $job->setItemsFailed($failedCount);
        $job->setLastIndexedVersionId($lastIndexedVersionId);
        $job->setCompletedAt(new \DateTimeImmutable());

        if (count($errors) > 0) {
            $job->setErrorDetail(implode("\n", $errors));
        }

        $this->entityManager->flush();

        return $job;
    }
}
