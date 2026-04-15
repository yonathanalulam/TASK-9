<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImportItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImportItem>
 */
class ImportItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportItem::class);
    }
}
