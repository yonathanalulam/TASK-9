<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContentVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContentVersion>
 */
class ContentVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentVersion::class);
    }
}
