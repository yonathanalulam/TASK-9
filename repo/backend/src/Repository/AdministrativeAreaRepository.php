<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AdministrativeArea;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdministrativeArea>
 */
class AdministrativeAreaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdministrativeArea::class);
    }
}
