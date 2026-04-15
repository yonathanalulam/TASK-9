<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ZoneOrderRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ZoneOrderRule>
 */
class ZoneOrderRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ZoneOrderRule::class);
    }
}
