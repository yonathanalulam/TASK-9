<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DuplicateResolutionEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DuplicateResolutionEvent>
 */
class DuplicateResolutionEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DuplicateResolutionEvent::class);
    }
}
