<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Audit;

use App\Entity\AuditEvent;
use App\Entity\AuditEventHash;
use App\Repository\AuditEventHashRepository;
use App\Service\Audit\HashChainService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(HashChainService::class)]
final class HashChainServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private AuditEventHashRepository&MockObject $repository;
    private HashChainService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(AuditEventHashRepository::class);
        $this->service = new HashChainService($this->entityManager, $this->repository);
    }

    public function testGenesisEventHasNullPreviousHashAndChainHashEqualsEventHash(): void
    {
        $event = $this->createAuditEvent('1');

        // Mock findOneBy to return null (no previous hash record).
        $this->repository->method('findOneBy')->willReturn(null);

        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(AuditEventHash::class));

        $hashRecord = $this->service->computeAndStore($event);

        self::assertNull($hashRecord->getPreviousHash());

        $expectedEventHash = $this->service->computeEventHash($event);
        self::assertSame($expectedEventHash, $hashRecord->getEventHash());
        self::assertSame($expectedEventHash, $hashRecord->getChainHash());
    }

    public function testChainedEventChainHashIsSha256OfPreviousChainHashPlusEventHash(): void
    {
        $event = $this->createAuditEvent('2');

        $previousChainHash = hash('sha256', 'previous-chain-data');

        // Create a mock previous AuditEventHash.
        $previousHashRecord = $this->createMock(AuditEventHash::class);
        $previousHashRecord->method('getChainHash')->willReturn($previousChainHash);

        $this->repository->method('findOneBy')->willReturn($previousHashRecord);

        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(AuditEventHash::class));

        $hashRecord = $this->service->computeAndStore($event);

        $expectedEventHash = $this->service->computeEventHash($event);
        $expectedChainHash = hash('sha256', $previousChainHash . $expectedEventHash);

        self::assertSame($previousChainHash, $hashRecord->getPreviousHash());
        self::assertSame($expectedEventHash, $hashRecord->getEventHash());
        self::assertSame($expectedChainHash, $hashRecord->getChainHash());
    }

    public function testEventHashIsDeterministicForSameInput(): void
    {
        $event = $this->createAuditEvent('1');

        $hash1 = $this->service->computeEventHash($event);
        $hash2 = $this->service->computeEventHash($event);

        self::assertSame($hash1, $hash2);
        self::assertSame(64, strlen($hash1), 'SHA-256 hash should be 64 hex characters.');
    }

    public function testEventHashDiffersForDifferentInput(): void
    {
        $event1 = $this->createAuditEvent('1', 'STORE_CREATED');
        $event2 = $this->createAuditEvent('2', 'STORE_UPDATED');

        $hash1 = $this->service->computeEventHash($event1);
        $hash2 = $this->service->computeEventHash($event2);

        self::assertNotSame($hash1, $hash2);
    }

    private function createAuditEvent(string $sequenceNumber, string $action = 'STORE_CREATED'): AuditEvent
    {
        return new AuditEvent(
            sequenceNumber: $sequenceNumber,
            action: $action,
            entityType: 'Store',
            entityId: hex2bin('0192e4a0b6d07e9b8e3f0a1b2c3d4e5f'),
            occurredAt: new \DateTimeImmutable('2025-01-15T10:30:00+00:00'),
            actorId: null,
            actorUsername: 'admin',
            oldValues: null,
            newValues: ['code' => 'TEST-001', 'name' => 'Test Store'],
        );
    }

}
