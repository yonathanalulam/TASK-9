<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuditEventHash;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditEventHash>
 */
class AuditEventHashRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditEventHash::class);
    }
}
