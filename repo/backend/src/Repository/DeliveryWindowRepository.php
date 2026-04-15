<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DeliveryWindow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DeliveryWindow>
 */
class DeliveryWindowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeliveryWindow::class);
    }
}
