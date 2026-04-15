<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\MutationQueue;

use App\Entity\DeliveryZone;
use App\Entity\MutationQueueLog;
use App\Entity\Store;
use App\Entity\User;
use App\Enum\RoleName;
use App\Repository\MutationQueueLogRepository;
use App\Service\Audit\AuditService;
use App\Service\Authorization\RbacService;
use App\Service\Authorization\ScopeResolver;
use App\Service\DeliveryZone\DeliveryZoneService;
use App\Service\MutationQueue\MutationReplayService;
use App\Service\Region\RegionService;
use App\Service\Store\StoreService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Proves that mutation replay enforces the same role + scope policy as the
 * normal controller/voter path for store updates, zone creates, and zone updates.
 *
 * Each test exercises the real MutationReplayService with mocked dependencies,
 * verifying that the batch result status reflects the authorization outcome.
 */
final class ReplayZoneCreateScopeTest extends TestCase
{
    private EntityManagerInterface $em;
    private MutationQueueLogRepository $logRepo;
    private StoreService $storeService;
    private DeliveryZoneService $zoneService;
    private ScopeResolver $scopeResolver;
    private RbacService $rbacService;
    private User $actor;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->logRepo = $this->createMock(MutationQueueLogRepository::class);
        $this->logRepo->method('findByMutationId')->willReturn(null);

        $this->storeService = $this->createMock(StoreService::class);
        $this->zoneService = $this->createMock(DeliveryZoneService::class);
        $this->scopeResolver = $this->createMock(ScopeResolver::class);
        $this->rbacService = $this->createMock(RbacService::class);
        $this->actor = $this->createMock(User::class);
    }

    private function buildService(): MutationReplayService
    {
        return new MutationReplayService(
            $this->em,
            $this->logRepo,
            $this->createMock(AuditService::class),
            $this->storeService,
            $this->createMock(RegionService::class),
            $this->zoneService,
            $this->scopeResolver,
            $this->rbacService,
        );
    }

    // ---------------------------------------------------------------
    //  Store Update — mirrors StoreVoter::canEdit()
    //  Required: (STORE_MANAGER | ADMINISTRATOR) + canAccessStore()
    // ---------------------------------------------------------------

    public function testStoreUpdateDeniedWhenRoleMissing(): void
    {
        // Actor has scope access but lacks the required role.
        $this->rbacService->method('hasAnyRole')->willReturn(false);
        $this->scopeResolver->method('canAccessStore')->willReturn(true);

        $store = $this->createMock(Store::class);
        $this->storeService->method('findById')->willReturn($store);

        $results = $this->buildService()->replayBatch([[
            'mutation_id' => 'store-upd-1',
            'client_id' => 'c1',
            'entity_type' => 'store',
            'entity_id' => 'store-id',
            'operation' => 'UPDATE',
            'payload' => ['name' => 'New Name'],
        ]], $this->actor);

        self::assertSame(MutationQueueLog::STATUS_REJECTED, $results[0]['status']);
        self::assertStringContainsString('Store Manager or Administrator', $results[0]['detail']);
    }

    public function testStoreUpdateDeniedWhenScopeMissing(): void
    {
        // Actor has the required role but lacks scope access.
        $this->rbacService->method('hasAnyRole')->willReturn(true);
        $this->scopeResolver->method('canAccessStore')->willReturn(false);

        $store = $this->createMock(Store::class);
        $this->storeService->method('findById')->willReturn($store);

        $results = $this->buildService()->replayBatch([[
            'mutation_id' => 'store-upd-2',
            'client_id' => 'c1',
            'entity_type' => 'store',
            'entity_id' => 'store-id',
            'operation' => 'UPDATE',
            'payload' => ['name' => 'New Name'],
        ]], $this->actor);

        self::assertSame(MutationQueueLog::STATUS_REJECTED, $results[0]['status']);
        self::assertStringContainsString('scope access', $results[0]['detail']);
    }

    public function testStoreUpdateAllowedWithRoleAndScope(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(true);
        $this->scopeResolver->method('canAccessStore')->willReturn(true);

        $store = $this->createMock(Store::class);
        $this->storeService->method('findById')->willReturn($store);
        $this->storeService->expects(self::once())->method('update');

        $results = $this->buildService()->replayBatch([[
            'mutation_id' => 'store-upd-3',
            'client_id' => 'c1',
            'entity_type' => 'store',
            'entity_id' => 'store-id',
            'operation' => 'UPDATE',
            'payload' => ['name' => 'New Name'],
        ]], $this->actor);

        self::assertSame(MutationQueueLog::STATUS_APPLIED, $results[0]['status']);
    }

    // ---------------------------------------------------------------
    //  Zone Create — mirrors DeliveryZoneVoter::canCreate(user, store)
    //  Required: (STORE_MANAGER | DISPATCHER | ADMINISTRATOR) + canAccessStore()
    // ---------------------------------------------------------------

    public function testZoneCreateDeniedWhenRoleMissing(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(false);

        $results = $this->buildService()->replayBatch([[
            'mutation_id' => 'zone-cr-1',
            'client_id' => 'c1',
            'entity_type' => 'delivery_zone',
            'operation' => 'CREATE',
            'payload' => ['store_id' => 'store-id', 'name' => 'Zone A'],
        ]], $this->actor);

        self::assertSame(MutationQueueLog::STATUS_REJECTED, $results[0]['status']);
        self::assertStringContainsString('Store Manager, Dispatcher, or Administrator', $results[0]['detail']);
    }

    public function testZoneCreateDeniedWhenStoreNotFound(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(true);
        $this->storeService->method('findById')->willReturn(null);

        $results = $this->buildService()->replayBatch([[
            'mutation_id' => 'zone-cr-2',
            'client_id' => 'c1',
            'entity_type' => 'delivery_zone',
            'operation' => 'CREATE',
            'payload' => ['store_id' => 'nonexistent', 'name' => 'Zone B'],
        ]], $this->actor);

        self::assertSame(MutationQueueLog::STATUS_REJECTED, $results[0]['status']);
        self::assertStringContainsString('not found', $results[0]['detail']);
    }

    public function testZoneCreateDeniedWhenScopeMissing(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(true);
        $this->storeService->method('findById')->willReturn($this->createMock(Store::class));
        $this->scopeResolver->method('canAccessStore')->willReturn(false);

        $results = $this->buildService()->replayBatch([[
            'mutation_id' => 'zone-cr-3',
            'client_id' => 'c1',
            'entity_type' => 'delivery_zone',
            'operation' => 'CREATE',
            'payload' => ['store_id' => 'store-id', 'name' => 'Zone C'],
        ]], $this->actor);

        self::assertSame(MutationQueueLog::STATUS_REJECTED, $results[0]['status']);
        self::assertStringContainsString('scope access', $results[0]['detail']);
    }

    public function testZoneCreateAllowedWithRoleAndScope(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(true);
        $this->storeService->method('findById')->willReturn($this->createMock(Store::class));
        $this->scopeResolver->method('canAccessStore')->willReturn(true);
        $this->zoneService->expects(self::once())->method('create');

        $results = $this->buildService()->replayBatch([[
            'mutation_id' => 'zone-cr-4',
            'client_id' => 'c1',
            'entity_type' => 'delivery_zone',
            'operation' => 'CREATE',
            'payload' => ['store_id' => 'store-id', 'name' => 'Zone D'],
        ]], $this->actor);

        self::assertSame(MutationQueueLog::STATUS_APPLIED, $results[0]['status']);
    }

    // ---------------------------------------------------------------
    //  Zone Update — mirrors DeliveryZoneVoter::canEdit(user, zone)
    //  Required: (STORE_MANAGER | DISPATCHER | ADMINISTRATOR) + canAccessDeliveryZone()
    // ---------------------------------------------------------------

    public function testZoneUpdateDeniedWhenRoleMissing(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(false);

        $results = $this->buildService()->replayBatch([[
            'mutation_id' => 'zone-upd-1',
            'client_id' => 'c1',
            'entity_type' => 'delivery_zone',
            'entity_id' => 'zone-id',
            'operation' => 'UPDATE',
            'payload' => ['name' => 'Updated Zone'],
        ]], $this->actor);

        self::assertSame(MutationQueueLog::STATUS_REJECTED, $results[0]['status']);
        self::assertStringContainsString('Store Manager, Dispatcher, or Administrator', $results[0]['detail']);
    }

    public function testZoneUpdateDeniedWhenScopeMissing(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(true);
        $zone = $this->createMock(DeliveryZone::class);
        $this->zoneService->method('findById')->willReturn($zone);
        $this->scopeResolver->method('canAccessDeliveryZone')->willReturn(false);

        $results = $this->buildService()->replayBatch([[
            'mutation_id' => 'zone-upd-2',
            'client_id' => 'c1',
            'entity_type' => 'delivery_zone',
            'entity_id' => 'zone-id',
            'operation' => 'UPDATE',
            'payload' => ['name' => 'Updated Zone'],
        ]], $this->actor);

        self::assertSame(MutationQueueLog::STATUS_REJECTED, $results[0]['status']);
        self::assertStringContainsString('scope access', $results[0]['detail']);
    }

    public function testZoneUpdateAllowedWithRoleAndScope(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(true);
        $zone = $this->createMock(DeliveryZone::class);
        $this->zoneService->method('findById')->willReturn($zone);
        $this->scopeResolver->method('canAccessDeliveryZone')->willReturn(true);
        $this->zoneService->expects(self::once())->method('update');

        $results = $this->buildService()->replayBatch([[
            'mutation_id' => 'zone-upd-3',
            'client_id' => 'c1',
            'entity_type' => 'delivery_zone',
            'entity_id' => 'zone-id',
            'operation' => 'UPDATE',
            'payload' => ['name' => 'Updated Zone'],
        ]], $this->actor);

        self::assertSame(MutationQueueLog::STATUS_APPLIED, $results[0]['status']);
    }
}
