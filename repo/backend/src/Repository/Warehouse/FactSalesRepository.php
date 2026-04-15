<?php

declare(strict_types=1);

namespace App\Repository\Warehouse;

use App\Entity\Warehouse\FactSales;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FactSales>
 */
class FactSalesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FactSales::class);
    }
}
