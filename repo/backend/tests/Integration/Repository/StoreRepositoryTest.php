<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\MdmRegion;
use App\Entity\Store;
use App\Enum\StoreType;
use App\Repository\StoreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class StoreRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private StoreRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->repository = $this->em->getRepository(Store::class);
    }

    public function testFindByRegionReturnsOnlyStoresInThatRegion(): void
    {
        $regionA = $this->createRegion('REGA', 'Region A');
        $regionB = $this->createRegion('REGB', 'Region B');

        $storeA1 = $this->createStore('SA001', 'Store A1', StoreType::STORE, $regionA);
        $storeA2 = $this->createStore('SA002', 'Store A2', StoreType::STORE, $regionA);
        $storeB1 = $this->createStore('SB001', 'Store B1', StoreType::STORE, $regionB);
        $this->em->flush();

        $results = $this->repository->findBy(['region' => $regionA]);

        self::assertCount(2, $results);

        $codes = array_map(static fn (Store $s) => $s->getCode(), $results);
        self::assertContains('SA001', $codes);
        self::assertContains('SA002', $codes);
        self::assertNotContains('SB001', $codes);
    }

    public function testFindByIsActiveReturnsOnlyActiveStores(): void
    {
        $region = $this->createRegion('REGC', 'Region C');

        $active1 = $this->createStore('ACT01', 'Active Store 1', StoreType::STORE, $region);
        $active2 = $this->createStore('ACT02', 'Active Store 2', StoreType::DARK_STORE, $region);
        $inactive = $this->createStore('INA01', 'Inactive Store', StoreType::STORE, $region);
        $inactive->setIsActive(false);
        $this->em->flush();

        $activeResults = $this->repository->findBy(['isActive' => true, 'region' => $region]);
        $inactiveResults = $this->repository->findBy(['isActive' => false, 'region' => $region]);

        self::assertCount(2, $activeResults);
        self::assertCount(1, $inactiveResults);
        self::assertSame('INA01', $inactiveResults[0]->getCode());
    }

    public function testFindByStatusFiltersCorrectly(): void
    {
        $region = $this->createRegion('REGD', 'Region D');

        $activeStore = $this->createStore('STA01', 'Active Status Store', StoreType::STORE, $region);
        $activeStore->setStatus('ACTIVE');

        $closedStore = $this->createStore('STC01', 'Closed Status Store', StoreType::STORE, $region);
        $closedStore->setStatus('CLOSED');

        $this->em->flush();

        $activeResults = $this->repository->findBy(['status' => 'ACTIVE', 'region' => $region]);
        $closedResults = $this->repository->findBy(['status' => 'CLOSED', 'region' => $region]);

        self::assertCount(1, $activeResults);
        self::assertSame('STA01', $activeResults[0]->getCode());

        self::assertCount(1, $closedResults);
        self::assertSame('STC01', $closedResults[0]->getCode());
    }

    public function testFindByStoreTypeFiltersCorrectly(): void
    {
        $region = $this->createRegion('REGE', 'Region E');

        $this->createStore('STR01', 'Regular Store', StoreType::STORE, $region);
        $this->createStore('DRK01', 'Dark Store', StoreType::DARK_STORE, $region);
        $this->createStore('DRK02', 'Dark Store 2', StoreType::DARK_STORE, $region);
        $this->em->flush();

        $storeResults = $this->repository->findBy(['storeType' => StoreType::STORE, 'region' => $region]);
        $darkResults = $this->repository->findBy(['storeType' => StoreType::DARK_STORE, 'region' => $region]);

        self::assertCount(1, $storeResults);
        self::assertSame('STR01', $storeResults[0]->getCode());

        self::assertCount(2, $darkResults);
    }

    public function testCountByReturnsCorrectTotals(): void
    {
        $region = $this->createRegion('REGF', 'Region F');

        $this->createStore('CNT01', 'Count 1', StoreType::STORE, $region);
        $this->createStore('CNT02', 'Count 2', StoreType::STORE, $region);
        $this->createStore('CNT03', 'Count 3', StoreType::STORE, $region);
        $this->em->flush();

        $count = $this->repository->count(['region' => $region]);

        self::assertSame(3, $count);
    }

    private function createRegion(string $code, string $name): MdmRegion
    {
        $region = new MdmRegion();
        $region->setCode($code);
        $region->setName($name);
        $region->setEffectiveFrom(new \DateTimeImmutable('2020-01-01'));

        $this->em->persist($region);

        return $region;
    }

    private function createStore(string $code, string $name, StoreType $type, MdmRegion $region): Store
    {
        $store = new Store();
        $store->setCode($code);
        $store->setName($name);
        $store->setStoreType($type);
        $store->setRegion($region);

        $this->em->persist($store);

        return $store;
    }
}
