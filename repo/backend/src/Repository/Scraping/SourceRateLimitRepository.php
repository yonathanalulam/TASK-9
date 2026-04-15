<?php

declare(strict_types=1);

namespace App\Repository\Scraping;

use App\Entity\Scraping\SourceRateLimit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SourceRateLimit>
 */
class SourceRateLimitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SourceRateLimit::class);
    }
}
