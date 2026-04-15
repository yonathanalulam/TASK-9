<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FieldAccessPolicy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FieldAccessPolicy>
 */
class FieldAccessPolicyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FieldAccessPolicy::class);
    }
}
