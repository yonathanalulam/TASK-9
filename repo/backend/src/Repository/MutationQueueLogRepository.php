<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MutationQueueLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MutationQueueLog>
 */
class MutationQueueLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MutationQueueLog::class);
    }

    public function findByMutationId(string $mutationId): ?MutationQueueLog
    {
        return $this->findOneBy(['mutationId' => $mutationId]);
    }

    /**
     * @return array{items: MutationQueueLog[], total: int}
     */
    public function findPaginated(int $page, int $perPage, ?string $status = null, ?string $entityType = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->orderBy('m.receivedAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('m.status = :status')
                ->setParameter('status', $status);
        }

        if ($entityType !== null) {
            $qb->andWhere('m.entityType = :entityType')
                ->setParameter('entityType', $entityType);
        }

        $total = (int) (clone $qb)
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
