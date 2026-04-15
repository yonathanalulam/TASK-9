<?php

declare(strict_types=1);

namespace App\Repository\Scraping;

use App\Entity\Scraping\ProxyPool;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProxyPool>
 */
class ProxyPoolRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProxyPool::class);
    }
}
