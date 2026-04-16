<?php

declare(strict_types=1);

namespace App\Tests\Integration\Store;

use App\Entity\MdmRegion;
use App\Entity\Store;
use App\Entity\User;
use App\Enum\StoreType;
use App\Service\Store\StoreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class StoreServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private StoreService $storeService;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->storeService = $container->get(StoreService::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
    }

    public function testCreateStorePersistsToDatabase(): void
    {
        $actor = $this->createTestUser('store_create_user');
        $region = $this->createActiveRegion('SC001', 'Store Create Region');

        $store = $this->storeService->create([
            'code' => 'INTG-CREATE-01',
            'name' => 'Integration Test Store',
            'store_type' => StoreType::STORE->value,
            'region_id' => $region->getId()->toRfc4122(),
            'timezone' => 'America/New_York',
            'city' => 'New York',
        ], $actor);

        self::assertInstanceOf(Store::class, $store);
        self::assertSame('INTG-CREATE-01', $store->getCode());
        self::assertSame('Integration Test Store', $store->getName());
        self::assertSame(StoreType::STORE, $store->getStoreType());
        self::assertSame('America/New_York', $store->getTimezone());
        self::assertSame('New York', $store->getCity());

        // Verify persistence by re-fetching from DB.
        $this->em->clear();
        $persisted = $this->em->getRepository(Store::class)->find($store->getId());

        self::assertNotNull($persisted);
        self::assertSame('INTG-CREATE-01', $persisted->getCode());
        self::assertSame('Integration Test Store', $persisted->getName());
        self::assertSame(StoreType::STORE, $persisted->getStoreType());
        self::assertSame($region->getId()->toRfc4122(), $persisted->getRegion()->getId()->toRfc4122());
        self::assertTrue($persisted->isActive());
    }

    public function testUpdateStoreReflectsChangeInDatabase(): void
    {
        $actor = $this->createTestUser('store_update_user');
        $region = $this->createActiveRegion('SU001', 'Store Update Region');

        $store = $this->storeService->create([
            'code' => 'INTG-UPDATE-01',
            'name' => 'Original Store Name',
            'store_type' => StoreType::STORE->value,
            'region_id' => $region->getId()->toRfc4122(),
        ], $actor);

        $updatedStore = $this->storeService->update($store, [
            'name' => 'Updated Store Name',
            'city' => 'San Francisco',
            'store_type' => StoreType::DARK_STORE->value,
            'change_reason' => 'Integration test update',
        ], $actor);

        self::assertSame('Updated Store Name', $updatedStore->getName());
        self::assertSame('San Francisco', $updatedStore->getCity());
        self::assertSame(StoreType::DARK_STORE, $updatedStore->getStoreType());

        // Verify in DB after clear.
        $this->em->clear();
        $reloaded = $this->em->getRepository(Store::class)->find($store->getId());

        self::assertNotNull($reloaded);
        self::assertSame('Updated Store Name', $reloaded->getName());
        self::assertSame('San Francisco', $reloaded->getCity());
        self::assertSame(StoreType::DARK_STORE, $reloaded->getStoreType());
    }

    public function testListStoresWithRegionFilter(): void
    {
        $actor = $this->createTestUser('store_list_user');
        $regionA = $this->createActiveRegion('SLA01', 'Region Alpha');
        $regionB = $this->createActiveRegion('SLB01', 'Region Beta');

        $this->storeService->create([
            'code' => 'INTG-LISTA-01',
            'name' => 'Store Alpha 1',
            'store_type' => StoreType::STORE->value,
            'region_id' => $regionA->getId()->toRfc4122(),
        ], $actor);

        $this->storeService->create([
            'code' => 'INTG-LISTA-02',
            'name' => 'Store Alpha 2',
            'store_type' => StoreType::DARK_STORE->value,
            'region_id' => $regionA->getId()->toRfc4122(),
        ], $actor);

        $this->storeService->create([
            'code' => 'INTG-LISTB-01',
            'name' => 'Store Beta 1',
            'store_type' => StoreType::STORE->value,
            'region_id' => $regionB->getId()->toRfc4122(),
        ], $actor);

        // List stores filtered by region A.
        $result = $this->storeService->list(
            page: 1,
            perPage: 50,
            regionId: $regionA->getId()->toRfc4122(),
        );

        self::assertArrayHasKey('items', $result);
        self::assertArrayHasKey('total', $result);
        self::assertSame(2, $result['total']);

        foreach ($result['items'] as $store) {
            self::assertSame(
                $regionA->getId()->toRfc4122(),
                $store->getRegion()->getId()->toRfc4122(),
            );
        }

        // List stores filtered by region B.
        $resultB = $this->storeService->list(
            page: 1,
            perPage: 50,
            regionId: $regionB->getId()->toRfc4122(),
        );

        self::assertSame(1, $resultB['total']);
        self::assertSame('Store Beta 1', $resultB['items'][0]->getName());
    }

    public function testCreateStoreVersionHistoryIsTracked(): void
    {
        $actor = $this->createTestUser('store_version_user');
        $region = $this->createActiveRegion('SV001', 'Store Version Region');

        $store = $this->storeService->create([
            'code' => 'INTG-VERS-01',
            'name' => 'Versioned Store',
            'store_type' => StoreType::STORE->value,
            'region_id' => $region->getId()->toRfc4122(),
        ], $actor);

        // Update the store to create a second version.
        $this->storeService->update($store, [
            'name' => 'Versioned Store Updated',
            'change_reason' => 'Version tracking test',
        ], $actor);

        $versions = $this->storeService->getVersionHistory($store);

        self::assertCount(2, $versions);
        self::assertSame(1, $versions[0]->getVersionNumber());
        self::assertSame('CREATED', $versions[0]->getChangeType());
        self::assertSame(2, $versions[1]->getVersionNumber());
        self::assertSame('UPDATED', $versions[1]->getChangeType());
    }

    private function createTestUser(string $username): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setDisplayName('Test ' . $username);
        $user->setStatus('ACTIVE');

        $hashedPassword = $this->passwordHasher->hashPassword($user, 'V@lid1Password!');
        $user->setPasswordHash($hashedPassword);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createActiveRegion(string $code, string $name): MdmRegion
    {
        $region = new MdmRegion();
        $region->setCode($code);
        $region->setName($name);
        $region->setIsActive(true);
        $region->setEffectiveFrom(new \DateTimeImmutable('-1 year'));

        $this->em->persist($region);
        $this->em->flush();

        return $region;
    }
}
