<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Dto\Response\PaginatedEnvelope;
use App\Entity\ImportBatch;
use App\Entity\ImportItem;
use App\Entity\User;
use App\Service\Import\DedupService;
use App\Service\Import\FingerprintService;
use App\Service\Import\NormalizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Security\Permission;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/imports')]
class ImportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NormalizationService $normalizationService,
        private readonly FingerprintService $fingerprintService,
        private readonly DedupService $dedupService,
    ) {
    }

    #[Route('', name: 'api_imports_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::IMPORT_CREATE);

        $body = json_decode($request->getContent(), true);

        if (!\is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        /** @var User $actor */
        $actor = $this->getUser();

        try {
            $batch = $this->createBatch($body, $actor);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                422,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeBatch($batch)), 201);
    }

    #[Route('', name: 'api_imports_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::IMPORT_VIEW);

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));
        $status = $request->query->get('status');

        $qb = $this->entityManager->createQueryBuilder()
            ->select('b')
            ->from(ImportBatch::class, 'b')
            ->orderBy('b.createdAt', 'DESC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('b.status = :status')
                ->setParameter('status', $status);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(b.id)')->getQuery()->getSingleScalarResult();

        /** @var ImportBatch[] $batches */
        $batches = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $data = array_map(
            fn (ImportBatch $batch) => $this->serializeBatch($batch),
            $batches,
        );

        return new JsonResponse(PaginatedEnvelope::wrap($data, $page, $perPage, $total));
    }

    #[Route('/{id}', name: 'api_imports_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::IMPORT_VIEW);

        $batch = $this->entityManager->getRepository(ImportBatch::class)->find($id);

        if ($batch === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Import batch not found.'),
                404,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeBatch($batch)));
    }

    #[Route('/{id}/items', name: 'api_imports_items', methods: ['GET'])]
    public function items(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::IMPORT_VIEW);

        $batch = $this->entityManager->getRepository(ImportBatch::class)->find($id);

        if ($batch === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Import batch not found.'),
                404,
            );
        }

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));
        $status = $request->query->get('status');

        $qb = $this->entityManager->createQueryBuilder()
            ->select('i')
            ->from(ImportItem::class, 'i')
            ->where('i.importBatch = :batch')
            ->setParameter('batch', $batch)
            ->orderBy('i.createdAt', 'ASC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('i.status = :status')
                ->setParameter('status', $status);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(i.id)')->getQuery()->getSingleScalarResult();

        /** @var ImportItem[] $items */
        $items = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $data = array_map(
            fn (ImportItem $item) => $this->serializeItem($item),
            $items,
        );

        return new JsonResponse(PaginatedEnvelope::wrap($data, $page, $perPage, $total));
    }

    private function createBatch(array $body, User $actor): ImportBatch
    {
        $sourceName = $body['source_name'] ?? null;
        $fileName = $body['file_name'] ?? null;
        $items = $body['items'] ?? null;

        if (!\is_string($sourceName) || $sourceName === '') {
            throw new \InvalidArgumentException('source_name is required.');
        }

        if (!\is_array($items) || \count($items) === 0) {
            throw new \InvalidArgumentException('items array is required and must not be empty.');
        }

        $batch = new ImportBatch();
        $batch->setSourceName($sourceName);
        $batch->setFileName($fileName);
        $batch->setCreatedBy($actor);
        $batch->setTotalItems(\count($items));
        $batch->setStatus('PROCESSING');

        $this->entityManager->persist($batch);

        $processedCount = 0;
        $mergedCount = 0;
        $reviewCount = 0;

        foreach ($items as $itemData) {
            if (!\is_array($itemData)) {
                throw new \InvalidArgumentException('Each item must be an object.');
            }

            $title = $itemData['title'] ?? null;

            if (!\is_string($title) || $title === '') {
                throw new \InvalidArgumentException('Each item must have a non-empty title.');
            }

            $company = $itemData['company'] ?? null;
            $location = $itemData['location'] ?? null;
            $rawBody = $itemData['body'] ?? null;

            $normalizedTitle = $this->normalizationService->normalize($title);
            $normalizedCompany = $company !== null ? $this->normalizationService->normalize($company) : null;
            $normalizedLocation = $location !== null ? $this->normalizationService->normalize($location) : null;
            $normalizedBody = $rawBody !== null ? $this->normalizationService->normalize($rawBody) : null;

            $fingerprint = $this->fingerprintService->computeFingerprint(
                $normalizedTitle,
                $normalizedCompany,
                $normalizedLocation,
                $normalizedBody,
            );

            $item = new ImportItem();
            $item->setImportBatch($batch);
            $item->setRawTitle($title);
            $item->setRawCompany($company);
            $item->setRawLocation($location);
            $item->setRawBody($rawBody);
            $item->setNormalizedTitle($normalizedTitle);
            $item->setNormalizedCompany($normalizedCompany);
            $item->setNormalizedLocation($normalizedLocation);
            $item->setDedupFingerprint($fingerprint);

            $this->entityManager->persist($item);

            // Run dedup
            $matches = $this->dedupService->findMatches($item);

            if (\count($matches) > 0) {
                $bestMatch = $matches[0];
                $item->setMatchedContentItemId($bestMatch['fingerprint']->getContentItemId());
                $item->setSimilarityScore(number_format($bestMatch['similarity'], 4, '.', ''));

                if ($bestMatch['action'] === 'AUTO_MERGE') {
                    $item->setStatus('AUTO_MERGED');
                    $item->setProcessedAt(new \DateTimeImmutable());
                    $mergedCount++;
                } else {
                    $item->setStatus('REVIEW_NEEDED');
                    $reviewCount++;
                }
            } else {
                $item->setStatus('NO_MATCH');
                $item->setProcessedAt(new \DateTimeImmutable());
            }

            $processedCount++;
        }

        $batch->setProcessedItems($processedCount);
        $batch->setMergedItems($mergedCount);
        $batch->setReviewItems($reviewCount);
        $batch->setStatus($reviewCount > 0 ? 'REVIEW_NEEDED' : 'COMPLETED');

        if ($reviewCount === 0) {
            $batch->setCompletedAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();

        return $batch;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBatch(ImportBatch $batch): array
    {
        return [
            'id' => $batch->getId()->toRfc4122(),
            'source_name' => $batch->getSourceName(),
            'file_name' => $batch->getFileName(),
            'status' => $batch->getStatus(),
            'total_items' => $batch->getTotalItems(),
            'processed_items' => $batch->getProcessedItems(),
            'merged_items' => $batch->getMergedItems(),
            'review_items' => $batch->getReviewItems(),
            'created_by' => $batch->getCreatedBy()->getId()->toRfc4122(),
            'created_at' => $batch->getCreatedAt()->format('c'),
            'completed_at' => $batch->getCompletedAt()?->format('c'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(ImportItem $item): array
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
}
