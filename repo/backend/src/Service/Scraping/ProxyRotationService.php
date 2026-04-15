<?php

declare(strict_types=1);

namespace App\Service\Scraping;

use App\Entity\Scraping\ProxyPool;
use App\Repository\Scraping\ProxyPoolRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Round-robin proxy rotation among ACTIVE proxies not in cooldown.
 */
class ProxyRotationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProxyPoolRepository $proxyRepo,
    ) {
    }

    /**
     * Get the next available proxy (round-robin by least-recently-used).
     */
    public function getNextProxy(): ?ProxyPool
    {
        $now = new \DateTimeImmutable();

        $qb = $this->proxyRepo->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.cooldownUntil IS NULL OR p.cooldownUntil < :now')
            ->setParameter('status', 'ACTIVE')
            ->setParameter('now', $now)
            ->orderBy('p.lastUsedAt', 'ASC')
            ->setMaxResults(1);

        $proxy = $qb->getQuery()->getOneOrNullResult();

        if ($proxy instanceof ProxyPool) {
            $proxy->setLastUsedAt($now);
            $this->em->flush();
        }

        return $proxy;
    }

    /**
     * Mark a proxy as banned and put it into cooldown.
     */
    public function markBanned(ProxyPool $proxy): void
    {
        $proxy->setStatus('BANNED');
        $proxy->setBanCount($proxy->getBanCount() + 1);
        $proxy->setCooldownUntil(new \DateTimeImmutable('+30 minutes'));

        $this->em->flush();
    }
}
