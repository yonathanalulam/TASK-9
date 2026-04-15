<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ZoneMapping;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ZoneMapping>
 */
class ZoneMappingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ZoneMapping::class);
    }
}
