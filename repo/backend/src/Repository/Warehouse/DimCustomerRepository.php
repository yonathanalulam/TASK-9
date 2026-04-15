<?php

declare(strict_types=1);

namespace App\Repository\Warehouse;

use App\Entity\Warehouse\DimCustomer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DimCustomer>
 */
class DimCustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DimCustomer::class);
    }
}
