<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Dto\Response\PaginatedEnvelope;
use App\Entity\ContentItem;
use App\Entity\DuplicateResolutionEvent;
use App\Entity\ImportItem;
use App\Entity\User;
use App\Service\Import\MergeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Security\Permission;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/dedup')]
class DedupReviewController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MergeService $mergeService,
    ) {
    }

    #[Route('/review', name: 'api_dedup_review_list', methods: ['GET'])]
    public function reviewList(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::DEDUP_REVIEW);

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));

        $qb = $this->entityManager->createQueryBuilder()
            ->select('i')
            ->from(ImportItem::class, 'i')
            ->where('i.status = :status')
            ->setParameter('status', 'REVIEW_NEEDED')
            ->orderBy('i.createdAt', 'ASC');

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(i.id)')->getQuery()->getSingleScalarResult();

        /** @var ImportItem[] $items */
        $items = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $data = array_map(
            fn (ImportItem $item) => $this->serializeImportItem($item),
            $items,
        );

        return new JsonResponse(PaginatedEnvelope::wrap($data, $page, $perPage, $total));
    }

    #[Route('/review/{id}/merge', name: 'api_dedup_review_merge', methods: ['POST'])]
    public function approveMerge(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::DEDUP_MERGE);

        $item = $this->entityManager->getRepository(ImportItem::class)->find($id);

        if ($item === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Import item not found.'),
                404,
            );
        }

        if ($item->getStatus() !== 'REVIEW_NEEDED') {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', sprintf(
                    'Import item must be in REVIEW_NEEDED status to merge. Current: %s',
                    $item->getStatus(),
                )),
                422,
            );
        }

        $matchedContentItemId = $item->getMatchedContentItemId();

        if ($matchedContentItemId === null) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'Import item has no matched content item.'),
                422,
            );
        }

        $target = $this->entityManager->getRepository(ContentItem::class)->find($matchedContentItemId);

        if ($target === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Matched content item not found.'),
                404,
            );
        }

        /** @var User $actor */
        $actor = $this->getUser();

        try {
            $event = $this->mergeService->merge($item, $target, 'MANUAL', $actor);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                422,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeResolutionEvent($event)));
    }

    #[Route('/review/{id}/reject', name: 'api_dedup_review_reject', methods: ['POST'])]
    public function rejectMerge(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::DEDUP_MERGE);

        $item = $this->entityManager->getRepository(ImportItem::class)->find($id);

        if ($item === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Import item not found.'),
                404,
            );
        }

        if ($item->getStatus() !== 'REVIEW_NEEDED') {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', sprintf(
                    'Import item must be in REVIEW_NEEDED status to reject. Current: %s',
                    $item->getStatus(),
                )),
                422,
            );
        }

        $item->setStatus('REJECTED');
        $item->setProcessedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return new JsonResponse(ApiEnvelope::wrap($this->serializeImportItem($item)));
    }

    #[Route('/unmerge/{id}', name: 'api_dedup_unmerge', methods: ['POST'])]
    public function unmerge(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::DEDUP_UNMERGE);

        $event = $this->entityManager->getRepository(DuplicateResolutionEvent::class)->find($id);

        if ($event === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Duplicate resolution event not found.'),
                404,
            );
        }

        if ($event->getUnmergedAt() !== null) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'This merge has already been unmerged.'),
                422,
            );
        }

        $body = json_decode($request->getContent(), true);
        $reason = \is_array($body) ? ($body['reason'] ?? 'No reason provided') : 'No reason provided';

        /** @var User $actor */
        $actor = $this->getUser();

        try {
            $this->mergeService->unmerge($event, $actor, $reason);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                422,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeResolutionEvent($event)));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeImportItem(ImportItem $item): array
    {
        return [
            'id' => $item->getId()->toRfc4122(),
            'import_batch_id' => $item->getImportBatch()->getId()->toRfc4122(),
            'raw_title' => $item->getRawTitle(),
            'raw_company' => $item->getRawCompany(),
            'raw_location' => $item->getRawLocation(),
            'raw_body' => $item->getRawBody(),
            'normalized_title' => $item->getNormalizedTitle(),
            'normalized_company' => $item->getNormalizedCompany(),
            'normalized_location' => $item->getNormalizedLocation(),
            'dedup_fingerprint' => $item->getDedupFingerprint(),
            'status' => $item->getStatus(),
            'matched_content_item_id' => $item->getMatchedContentItemId()?->toRfc4122(),
            'similarity_score' => $item->getSimilarityScore(),
            'created_at' => $item->getCreatedAt()->format('c'),
            'processed_at' => $item->getProcessedAt()?->format('c'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeResolutionEvent(DuplicateResolutionEvent $event): array
    {
        return [
            'id' => $event->getId()->toRfc4122(),
            'source_import_item_id' => $event->getSourceImportItemId()->toRfc4122(),
            'target_content_item_id' => $event->getTargetContentItemId()->toRfc4122(),
            'merge_type' => $event->getMergeType(),
            'similarity_score' => $event->getSimilarityScore(),
            'merged_by' => $event->getMergedBy()?->getId()->toRfc4122(),
            'merged_at' => $event->getMergedAt()->format('c'),
            'unmerged_at' => $event->getUnmergedAt()?->format('c'),
            'unmerged_by' => $event->getUnmergedBy()?->getId()->toRfc4122(),
            'unmerge_reason' => $event->getUnmergeReason(),
        ];
    }
}
