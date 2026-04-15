<?php

declare(strict_types=1);

namespace App\Repository\Warehouse;

use App\Entity\Warehouse\DimProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DimProduct>
 */
class DimProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DimProduct::class);
    }
}
