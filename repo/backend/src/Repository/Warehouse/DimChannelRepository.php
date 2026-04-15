<?php

declare(strict_types=1);

namespace App\Repository\Warehouse;

use App\Entity\Warehouse\DimChannel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DimChannel>
 */
class DimChannelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DimChannel::class);
    }
}
