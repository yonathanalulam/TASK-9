<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Dto\Response\PaginatedEnvelope;
use App\Entity\RetentionCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Security\Permission;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/retention')]
class RetentionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/cases', name: 'api_retention_cases_list', methods: ['GET'])]
    public function listCases(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::COMPLIANCE_VIEW);

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));
        $status = $request->query->get('status');

        $qb = $this->entityManager->createQueryBuilder()
            ->select('rc')
            ->from(RetentionCase::class, 'rc')
            ->orderBy('rc.eligibleAt', 'ASC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('rc.status = :status')
                ->setParameter('status', $status);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(rc.id)')->getQuery()->getSingleScalarResult();

        /** @var RetentionCase[] $cases */
        $cases = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $data = array_map(
            fn (RetentionCase $case) => $this->serializeRetentionCase($case),
            $cases,
        );

        return new JsonResponse(PaginatedEnvelope::wrap($data, $page, $perPage, $total));
    }

    #[Route('/cases/{id}/schedule', name: 'api_retention_cases_schedule', methods: ['POST'])]
    public function schedule(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::COMPLIANCE_MANAGE);

        $case = $this->entityManager->getRepository(RetentionCase::class)->find($id);

        if ($case === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Retention case not found.'),
                404,
            );
        }

        if ($case->getStatus() !== 'ELIGIBLE') {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', sprintf(
                    'Retention case must be in ELIGIBLE status to schedule. Current: %s',
                    $case->getStatus(),
                )),
                422,
            );
        }

        $case->setStatus('SCHEDULED');
        $case->setScheduledAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return new JsonResponse(ApiEnvelope::wrap($this->serializeRetentionCase($case)));
    }

    #[Route('/stats', name: 'api_retention_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::COMPLIANCE_VIEW);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('rc.status, COUNT(rc.id) as count')
            ->from(RetentionCase::class, 'rc')
            ->groupBy('rc.status');

        $rows = $qb->getQuery()->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return new JsonResponse(ApiEnvelope::wrap([
            'counts_by_status' => $counts,
            'total' => array_sum($counts),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRetentionCase(RetentionCase $case): array
    {
        return [
            'id' => $case->getId()->toRfc4122(),
            'entity_type' => $case->getEntityType(),
            'entity_id' => bin2hex($case->getEntityId()),
            'status' => $case->getStatus(),
            'retention_days' => $case->getRetentionDays(),
            'eligible_at' => $case->getEligibleAt()->format('c'),
            'scheduled_at' => $case->getScheduledAt()?->format('c'),
            'executed_at' => $case->getExecutedAt()?->format('c'),
            'action_taken' => $case->getActionTaken(),
            'error_detail' => $case->getErrorDetail(),
        ];
    }
}
