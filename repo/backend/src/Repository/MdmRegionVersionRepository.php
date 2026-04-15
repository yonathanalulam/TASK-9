<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MdmRegionVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MdmRegionVersion>
 */
class MdmRegionVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MdmRegionVersion::class);
    }
}
