<?php

declare(strict_types=1);

namespace App\Repository\Scraping;

use App\Entity\Scraping\SourceDefinition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SourceDefinition>
 */
class SourceDefinitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SourceDefinition::class);
    }
}
