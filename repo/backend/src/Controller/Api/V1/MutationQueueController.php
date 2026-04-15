<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Dto\Response\PaginatedEnvelope;
use App\Entity\MutationQueueLog;
use App\Entity\User;
use App\Service\MutationQueue\MutationReplayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Security\Permission;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/mutations')]
class MutationQueueController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MutationReplayService $replayService,
    ) {
    }

    #[Route('/replay', name: 'api_mutations_replay', methods: ['POST'])]
    public function replay(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MUTATION_REPLAY);

        $body = json_decode($request->getContent(), true);

        if (!is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        $mutations = $body['mutations'] ?? null;

        if (!is_array($mutations) || count($mutations) === 0) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'The "mutations" array is required and must not be empty.'),
                422,
            );
        }

        /** @var User $actor */
        $actor = $this->getUser();

        $results = $this->replayService->replayBatch($mutations, $actor);

        return new JsonResponse(ApiEnvelope::wrap($results));
    }

    #[Route('', name: 'api_mutations_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::MUTATION_VIEW_ADMIN);

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));
        $status = $request->query->get('status');
        $entityType = $request->query->get('entity_type');

        $qb = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(MutationQueueLog::class, 'm')
            ->orderBy('m.receivedAt', 'DESC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('m.status = :status')
                ->setParameter('status', $status);
        }

        if ($entityType !== null && $entityType !== '') {
            $qb->andWhere('m.entityType = :entityType')
                ->setParameter('entityType', $entityType);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(m.id)')->getQuery()->getSingleScalarResult();

        /** @var MutationQueueLog[] $items */
        $items = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $data = array_map(
            fn (MutationQueueLog $log) => $this->serializeMutationLog($log),
            $items,
        );

        return new JsonResponse(PaginatedEnvelope::wrap($data, $page, $perPage, $total));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMutationLog(MutationQueueLog $log): array
    {
        return [
            'id' => $log->getId()->toRfc4122(),
            'client_id' => $log->getClientId(),
            'mutation_id' => $log->getMutationId(),
            'entity_type' => $log->getEntityType(),
            'entity_id' => $log->getEntityId(),
            'operation' => $log->getOperation(),
            'payload' => $log->getPayload(),
            'status' => $log->getStatus(),
            'conflict_detail' => $log->getConflictDetail(),
            'received_at' => $log->getReceivedAt()->format('c'),
            'processed_at' => $log->getProcessedAt()?->format('c'),
        ];
    }
}
