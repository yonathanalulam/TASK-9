<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContentSearchIndex;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContentSearchIndex>
 */
class ContentSearchIndexRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentSearchIndex::class);
    }
}
