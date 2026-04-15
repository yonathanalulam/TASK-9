<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RetentionCase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RetentionCase>
 */
class RetentionCaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RetentionCase::class);
    }
}
