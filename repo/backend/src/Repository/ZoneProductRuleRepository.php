<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ZoneProductRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ZoneProductRule>
 */
class ZoneProductRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ZoneProductRule::class);
    }
}
