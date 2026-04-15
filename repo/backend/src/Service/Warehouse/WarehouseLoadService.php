<?php

declare(strict_types=1);

namespace App\Service\Warehouse;

use App\Entity\Warehouse\WarehouseLoadRun;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Orchestrates the full ETL pipeline: dimensions then facts.
 */
class WarehouseLoadService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DimensionLoaderService $dimensionLoader,
        private readonly FactLoaderService $factLoader,
    ) {
    }

    /**
     * @param string $loadType FULL or INCREMENTAL
     */
    public function execute(string $loadType = 'FULL'): WarehouseLoadRun
    {
        $run = new WarehouseLoadRun();
        $run->setLoadType($loadType);
        $run->setSourceTables(['content_items', 'users', 'stores', 'mdm_regions']);
        $run->setStatus('RUNNING');
        $run->setStartedAt(new \DateTimeImmutable());

        $this->em->persist($run);
        $this->em->flush();

        try {
            // Phase 1: Load dimensions (SCD Type 2)
            $dimStats = $this->dimensionLoader->loadDimensions();

            // Phase 2: Load facts
            $this->factLoader->loadFacts($run);

            $status = $run->getRowsRejected() > 0 ? 'PARTIAL' : 'SUCCEEDED';
            $run->setStatus($status);
        } catch (\Throwable $e) {
            $run->setStatus('FAILED');
            $run->setErrorDetail($e->getMessage());
        }

        $run->setCompletedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $run;
    }
}
