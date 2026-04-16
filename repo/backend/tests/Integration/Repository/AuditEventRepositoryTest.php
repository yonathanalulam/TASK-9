<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\AuditEvent;
use App\Repository\AuditEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class AuditEventRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private AuditEventRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->repository = $this->em->getRepository(AuditEvent::class);
    }

    public function testFindByEntityTypeReturnsOnlyMatchingEvents(): void
    {
        $storeEntityId = Uuid::v7()->toBinary();
        $contentEntityId = Uuid::v7()->toBinary();

        $this->createAuditEvent('1001', 'CREATE', 'Store', $storeEntityId);
        $this->createAuditEvent('1002', 'UPDATE', 'Store', $storeEntityId);
        $this->createAuditEvent('1003', 'CREATE', 'ContentItem', $contentEntityId);
        $this->em->flush();

        $storeEvents = $this->repository->findBy(['entityType' => 'Store']);
        $contentEvents = $this->repository->findBy(['entityType' => 'ContentItem']);

        self::assertGreaterThanOrEqual(2, count($storeEvents));
        self::assertGreaterThanOrEqual(1, count($contentEvents));

        // Verify all returned store events have correct entity type.
        foreach ($storeEvents as $event) {
            self::assertSame('Store', $event->getEntityType());
        }

        foreach ($contentEvents as $event) {
            self::assertSame('ContentItem', $event->getEntityType());
        }
    }

    public function testFindByActionFiltersCorrectly(): void
    {
        $entityId = Uuid::v7()->toBinary();

        $this->createAuditEvent('2001', 'CREATE', 'TestAction', $entityId);
        $this->createAuditEvent('2002', 'UPDATE', 'TestAction', $entityId);
        $this->createAuditEvent('2003', 'DELETE', 'TestAction', $entityId);
        $this->em->flush();

        $creates = $this->repository->findBy(['action' => 'CREATE', 'entityType' => 'TestAction']);
        $updates = $this->repository->findBy(['action' => 'UPDATE', 'entityType' => 'TestAction']);
        $deletes = $this->repository->findBy(['action' => 'DELETE', 'entityType' => 'TestAction']);

        self::assertCount(1, $creates);
        self::assertCount(1, $updates);
        self::assertCount(1, $deletes);

        self::assertSame('CREATE', $creates[0]->getAction());
        self::assertSame('UPDATE', $updates[0]->getAction());
        self::assertSame('DELETE', $deletes[0]->getAction());
    }

    public function testSequenceNumbersAreUniqueAndPreserved(): void
    {
        $entityId = Uuid::v7()->toBinary();

        $event1 = $this->createAuditEvent('3001', 'CREATE', 'SeqTest', $entityId);
        $event2 = $this->createAuditEvent('3002', 'UPDATE', 'SeqTest', $entityId);
        $event3 = $this->createAuditEvent('3003', 'UPDATE', 'SeqTest', $entityId);
        $this->em->flush();

        // Clear identity map and reload.
        $this->em->clear();

        $results = $this->repository->createQueryBuilder('a')
            ->where('a.entityType = :type')
            ->setParameter('type', 'SeqTest')
            ->orderBy('a.sequenceNumber', 'ASC')
            ->getQuery()
            ->getResult();

        self::assertCount(3, $results);

        // Verify sequence numbers are in ascending order.
        self::assertSame('3001', $results[0]->getSequenceNumber());
        self::assertSame('3002', $results[1]->getSequenceNumber());
        self::assertSame('3003', $results[2]->getSequenceNumber());

        // Verify each sequence number is strictly greater than previous.
        for ($i = 1; $i < count($results); $i++) {
            self::assertGreaterThan(
                (int) $results[$i - 1]->getSequenceNumber(),
                (int) $results[$i]->getSequenceNumber(),
                'Sequence numbers must be in strictly ascending order.'
            );
        }
    }

    public function testOrderByOccurredAtDescendingShowsNewestFirst(): void
    {
        $entityId = Uuid::v7()->toBinary();

        $this->createAuditEvent('4001', 'CREATE', 'OrderTest', $entityId, new \DateTimeImmutable('2026-01-01 10:00:00'));
        $this->createAuditEvent('4002', 'UPDATE', 'OrderTest', $entityId, new \DateTimeImmutable('2026-01-02 10:00:00'));
        $this->createAuditEvent('4003', 'UPDATE', 'OrderTest', $entityId, new \DateTimeImmutable('2026-01-03 10:00:00'));
        $this->em->flush();

        $results = $this->repository->createQueryBuilder('a')
            ->where('a.entityType = :type')
            ->setParameter('type', 'OrderTest')
            ->orderBy('a.occurredAt', 'DESC')
            ->getQuery()
            ->getResult();

        self::assertCount(3, $results);

        // Newest first.
        self::assertSame('4003', $results[0]->getSequenceNumber());
        self::assertSame('4002', $results[1]->getSequenceNumber());
        self::assertSame('4001', $results[2]->getSequenceNumber());
    }

    public function testOldAndNewValuesArePersistedAsJson(): void
    {
        $entityId = Uuid::v7()->toBinary();
        $oldValues = ['status' => 'DRAFT'];
        $newValues = ['status' => 'PUBLISHED'];

        $event = new AuditEvent(
            sequenceNumber: '5001',
            action: 'UPDATE',
            entityType: 'JsonTest',
            entityId: $entityId,
            occurredAt: new \DateTimeImmutable(),
            oldValues: $oldValues,
            newValues: $newValues,
        );
        $this->em->persist($event);
        $this->em->flush();

        // Clear identity map and reload.
        $this->em->clear();

        $loaded = $this->repository->find($event->getId());

        self::assertNotNull($loaded);
        self::assertSame(['status' => 'DRAFT'], $loaded->getOldValues());
        self::assertSame(['status' => 'PUBLISHED'], $loaded->getNewValues());
    }

    public function testFilterByEntityTypeAndEntityIdCombination(): void
    {
        $entityIdA = Uuid::v7()->toBinary();
        $entityIdB = Uuid::v7()->toBinary();

        $this->createAuditEvent('6001', 'CREATE', 'ComboFilter', $entityIdA);
        $this->createAuditEvent('6002', 'UPDATE', 'ComboFilter', $entityIdA);
        $this->createAuditEvent('6003', 'CREATE', 'ComboFilter', $entityIdB);
        $this->em->flush();

        $resultsA = $this->repository->findBy(['entityType' => 'ComboFilter', 'entityId' => $entityIdA]);
        $resultsB = $this->repository->findBy(['entityType' => 'ComboFilter', 'entityId' => $entityIdB]);

        self::assertCount(2, $resultsA);
        self::assertCount(1, $resultsB);
    }

    private function createAuditEvent(
        string $sequenceNumber,
        string $action,
        string $entityType,
        string $entityId,
        ?\DateTimeImmutable $occurredAt = null,
    ): AuditEvent {
        $event = new AuditEvent(
            sequenceNumber: $sequenceNumber,
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            occurredAt: $occurredAt ?? new \DateTimeImmutable(),
        );

        $this->em->persist($event);

        return $event;
    }
}
