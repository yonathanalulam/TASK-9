<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContentFingerprint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContentFingerprint>
 */
class ContentFingerprintRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentFingerprint::class);
    }
}
