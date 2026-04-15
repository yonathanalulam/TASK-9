<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DeliveryZoneVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DeliveryZoneVersion>
 */
class DeliveryZoneVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeliveryZoneVersion::class);
    }
}
