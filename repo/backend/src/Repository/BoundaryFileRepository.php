<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BoundaryFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BoundaryFile>
 */
class BoundaryFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BoundaryFile::class);
    }
}
