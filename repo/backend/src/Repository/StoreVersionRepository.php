<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StoreVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StoreVersion>
 */
class StoreVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StoreVersion::class);
    }
}
