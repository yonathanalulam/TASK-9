<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\ImportBatch;
use App\Entity\User;
use App\Repository\ImportBatchRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ImportBatchRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ImportBatchRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->repository = $this->em->getRepository(ImportBatch::class);
    }

    public function testFindByStatusReturnsOnlyMatchingBatches(): void
    {
        $user = $this->createTestUser('batch_status_user');

        $pending = $this->createBatch('source-a', 'PENDING', $user);
        $completed = $this->createBatch('source-b', 'COMPLETED', $user);
        $failed = $this->createBatch('source-c', 'FAILED', $user);
        $this->em->flush();

        $pendingResults = $this->repository->findBy(['status' => 'PENDING', 'createdBy' => $user]);
        $completedResults = $this->repository->findBy(['status' => 'COMPLETED', 'createdBy' => $user]);
        $failedResults = $this->repository->findBy(['status' => 'FAILED', 'createdBy' => $user]);

        self::assertCount(1, $pendingResults);
        self::assertCount(1, $completedResults);
        self::assertCount(1, $failedResults);

        self::assertSame('PENDING', $pendingResults[0]->getStatus());
        self::assertSame('source-a', $pendingResults[0]->getSourceName());

        self::assertSame('COMPLETED', $completedResults[0]->getStatus());
        self::assertSame('source-b', $completedResults[0]->getSourceName());

        self::assertSame('FAILED', $failedResults[0]->getStatus());
        self::assertSame('source-c', $failedResults[0]->getSourceName());
    }

    public function testOrderByIdDescReturnsNewestFirst(): void
    {
        $user = $this->createTestUser('batch_order_user');

        // Create batches sequentially. UUIDv7 IDs are time-ordered, so
        // ordering by id DESC is equivalent to newest-first.
        $batch1 = $this->createBatch('first-batch', 'PENDING', $user);
        $batch2 = $this->createBatch('second-batch', 'PENDING', $user);
        $batch3 = $this->createBatch('third-batch', 'PENDING', $user);
        $this->em->flush();

        $results = $this->repository->createQueryBuilder('b')
            ->where('b.createdBy = :userId')
            ->setParameter('userId', $user->getId(), 'uuid')
            ->orderBy('b.id', 'DESC')
            ->getQuery()
            ->getResult();

        self::assertCount(3, $results);

        // UUIDv7 ordering: third > second > first
        self::assertSame('third-batch', $results[0]->getSourceName());
        self::assertSame('second-batch', $results[1]->getSourceName());
        self::assertSame('first-batch', $results[2]->getSourceName());
    }

    public function testCountByStatusReturnsCorrectTotals(): void
    {
        $user = $this->createTestUser('batch_count_user');

        $this->createBatch('cnt-pending-1', 'PENDING', $user);
        $this->createBatch('cnt-pending-2', 'PENDING', $user);
        $this->createBatch('cnt-completed-1', 'COMPLETED', $user);
        $this->em->flush();

        $pendingCount = $this->repository->count(['status' => 'PENDING', 'createdBy' => $user]);
        $completedCount = $this->repository->count(['status' => 'COMPLETED', 'createdBy' => $user]);

        self::assertSame(2, $pendingCount);
        self::assertSame(1, $completedCount);
    }

    public function testBatchItemCountsArePersisted(): void
    {
        $user = $this->createTestUser('batch_items_user');

        $batch = $this->createBatch('items-batch', 'PENDING', $user);
        $batch->setTotalItems(100);
        $batch->setProcessedItems(50);
        $batch->setMergedItems(45);
        $batch->setReviewItems(5);
        $this->em->flush();

        // Clear identity map to force fresh load.
        $this->em->clear();

        $loaded = $this->repository->find($batch->getId());

        self::assertNotNull($loaded);
        self::assertSame(100, $loaded->getTotalItems());
        self::assertSame(50, $loaded->getProcessedItems());
        self::assertSame(45, $loaded->getMergedItems());
        self::assertSame(5, $loaded->getReviewItems());
    }

    private function createTestUser(string $username): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setDisplayName('Test User ' . $username);
        $user->setPasswordHash('$2y$13$dummy_hash_for_testing_purposes_only');
        $user->setStatus('ACTIVE');

        $this->em->persist($user);

        return $user;
    }

    private function createBatch(string $sourceName, string $status, User $createdBy): ImportBatch
    {
        $batch = new ImportBatch();
        $batch->setSourceName($sourceName);
        $batch->setStatus($status);
        $batch->setCreatedBy($createdBy);

        $this->em->persist($batch);

        return $batch;
    }
}
