<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Dto\Response\PaginatedEnvelope;
use App\Entity\Warehouse\WarehouseLoadRun;
use App\Repository\Warehouse\WarehouseLoadRunRepository;
use App\Service\Warehouse\WarehouseLoadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Security\Permission;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/warehouse/loads')]
class WarehouseLoadController extends AbstractController
{
    public function __construct(
        private readonly WarehouseLoadRunRepository $loadRunRepo,
        private readonly WarehouseLoadService $loadService,
    ) {
    }

    #[Route('', name: 'api_warehouse_loads_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::WAREHOUSE_VIEW);

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));

        $qb = $this->loadRunRepo->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC');

        $total = (int) (clone $qb)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $data = array_map(fn (WarehouseLoadRun $r) => $this->serializeRun($r), $items);

        return new JsonResponse(PaginatedEnvelope::wrap($data, $page, $perPage, $total));
    }

    #[Route('/{id}', name: 'api_warehouse_loads_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::WAREHOUSE_VIEW);

        $run = $this->loadRunRepo->find($id);

        if ($run === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Warehouse load run not found.'),
                404,
            );
        }

        $data = $this->serializeRun($run);
        $data['rejected_detail'] = $run->getRejectedDetail();

        return new JsonResponse(ApiEnvelope::wrap($data));
    }

    #[Route('/trigger', name: 'api_warehouse_loads_trigger', methods: ['POST'])]
    public function trigger(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::WAREHOUSE_TRIGGER);

        $body = json_decode($request->getContent(), true);
        $loadType = $body['load_type'] ?? 'FULL';

        if (!in_array($loadType, ['FULL', 'INCREMENTAL'], true)) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'load_type must be FULL or INCREMENTAL.'),
                422,
            );
        }

        $run = $this->loadService->execute($loadType);

        return new JsonResponse(ApiEnvelope::wrap($this->serializeRun($run)), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRun(WarehouseLoadRun $run): array
    {
        return [
            'id' => $run->getId()->toRfc4122(),
            'load_type' => $run->getLoadType(),
            'source_tables' => $run->getSourceTables(),
            'status' => $run->getStatus(),
            'rows_extracted' => $run->getRowsExtracted(),
            'rows_loaded' => $run->getRowsLoaded(),
            'rows_rejected' => $run->getRowsRejected(),
            'started_at' => $run->getStartedAt()?->format('c'),
            'completed_at' => $run->getCompletedAt()?->format('c'),
            'error_detail' => $run->getErrorDetail(),
            'created_at' => $run->getCreatedAt()->format('c'),
        ];
    }
}
