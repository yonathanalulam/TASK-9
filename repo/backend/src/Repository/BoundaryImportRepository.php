<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BoundaryImport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BoundaryImport>
 */
class BoundaryImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BoundaryImport::class);
    }

    public function findByHash(string $hash): ?BoundaryImport
    {
        return $this->findOneBy(['fileHash' => $hash]);
    }

    /**
     * @return array{items: BoundaryImport[], total: int}
     */
    public function findPaginated(int $page, int $perPage, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('bi')
            ->orderBy('bi.createdAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('bi.status = :status')
                ->setParameter('status', $status);
        }

        $total = (int) (clone $qb)
            ->select('COUNT(bi.id)')
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
