<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\ContentFingerprint;
use App\Entity\ContentItem;
use App\Entity\DuplicateResolutionEvent;
use App\Entity\ImportItem;
use App\Entity\User;
use App\Service\Audit\AuditService;
use Doctrine\ORM\EntityManagerInterface;

class MergeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * Merge an import item into an existing content item.
     */
    public function merge(
        ImportItem $item,
        ContentItem $target,
        string $mergeType,
        ?User $actor,
    ): DuplicateResolutionEvent {
        $event = new DuplicateResolutionEvent();
        $event->setSourceImportItemId($item->getId());
        $event->setTargetContentItemId($target->getId());
        $event->setMergeType($mergeType);
        $event->setSimilarityScore($item->getSimilarityScore() ?? '0.0000');
        $event->setMergedBy($actor);

        // Update the import item status
        $item->setMatchedContentItemId($target->getId());
        $item->setStatus($mergeType === 'AUTO' ? 'AUTO_MERGED' : 'MANUALLY_MERGED');
        $item->setProcessedAt(new \DateTimeImmutable());

        // Record the fingerprint association
        $fingerprint = new ContentFingerprint();
        $fingerprint->setFingerprint($item->getDedupFingerprint());
        $fingerprint->setContentItemId($target->getId());
        $fingerprint->setSourceImportItemId($item->getId());

        $this->entityManager->persist($event);
        $this->entityManager->persist($fingerprint);
        $this->entityManager->flush();

        $this->auditService->record(
            action: 'IMPORT_MERGE',
            entityType: 'ContentItem',
            entityId: $target->getId()->toRfc4122(),
            oldValues: null,
            newValues: [
                'merge_type' => $mergeType,
                'source_import_item_id' => $item->getId()->toRfc4122(),
                'similarity_score' => $item->getSimilarityScore(),
            ],
            actor: $actor,
        );

        return $event;
    }

    /**
     * Unmerge a previously merged duplicate resolution event.
     */
    public function unmerge(
        DuplicateResolutionEvent $event,
        User $actor,
        string $reason,
    ): void {
        $event->setUnmergedAt(new \DateTimeImmutable());
        $event->setUnmergedBy($actor);
        $event->setUnmergeReason($reason);

        $this->entityManager->flush();

        $this->auditService->record(
            action: 'IMPORT_UNMERGE',
            entityType: 'ContentItem',
            entityId: $event->getTargetContentItemId()->toRfc4122(),
            oldValues: [
                'merge_type' => $event->getMergeType(),
                'source_import_item_id' => $event->getSourceImportItemId()->toRfc4122(),
            ],
            newValues: [
                'unmerge_reason' => $reason,
            ],
            actor: $actor,
        );
    }
}
