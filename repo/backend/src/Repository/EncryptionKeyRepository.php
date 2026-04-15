<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EncryptionKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EncryptionKey>
 */
class EncryptionKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EncryptionKey::class);
    }
}
