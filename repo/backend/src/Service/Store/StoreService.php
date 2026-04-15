<?php

declare(strict_types=1);

namespace App\Service\Store;

use App\Entity\Store;
use App\Entity\StoreVersion;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\StoreType;
use App\Repository\MdmRegionRepository;
use App\Repository\StoreRepository;
use App\Repository\StoreVersionRepository;
use App\Service\Audit\AuditService;
use Doctrine\ORM\EntityManagerInterface;

class StoreService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StoreRepository $storeRepository,
        private readonly StoreVersionRepository $versionRepository,
        private readonly MdmRegionRepository $regionRepository,
        private readonly AuditService $auditService,
    ) {
    }

    public function create(array $data, User $actor): Store
    {
        $code = $data['code'] ?? '';
        $name = $data['name'] ?? '';
        $storeType = $data['store_type'] ?? null;
        $regionId = $data['region_id'] ?? null;
        $changeReason = $data['change_reason'] ?? null;

        // Validate code format.
        if (!is_string($code) || !preg_match('/^[A-Z0-9_\-]{3,20}$/', $code)) {
            throw new \InvalidArgumentException('Store code must match ^[A-Z0-9_\\-]{3,20}$.');
        }

        // Validate code uniqueness.
        $existing = $this->storeRepository->findOneBy(['code' => $code]);
        if ($existing !== null) {
            throw new \InvalidArgumentException('Store code is already in use.');
        }

        // Validate name.
        if (!is_string($name) || mb_strlen($name) < 1 || mb_strlen($name) > 150) {
            throw new \InvalidArgumentException('Store name must be between 1 and 150 characters.');
        }

        // Validate store type matches enum.
        $storeTypeEnum = StoreType::tryFrom($storeType ?? '');
        if ($storeTypeEnum === null) {
            $allowed = implode(', ', array_map(static fn (StoreType $t) => $t->value, StoreType::cases()));
            throw new \InvalidArgumentException(sprintf('Store type must be one of: %s.', $allowed));
        }

        // Validate region exists and is active.
        if ($regionId === null || $regionId === '') {
            throw new \InvalidArgumentException('Region is required.');
        }

        $region = $this->regionRepository->find($regionId);
        if ($region === null) {
            throw new \InvalidArgumentException('Region not found.');
        }
        if (!$region->isActive()) {
            throw new \InvalidArgumentException('Region is not active.');
        }

        $store = new Store();
        $store->setCode($code);
        $store->setName($name);
        $store->setStoreType($storeTypeEnum);
        $store->setRegion($region);

        if (array_key_exists('status', $data) && is_string($data['status'])) {
            $store->setStatus($data['status']);
        }
        if (array_key_exists('timezone', $data) && is_string($data['timezone'])) {
            $store->setTimezone($data['timezone']);
        }
        if (array_key_exists('address_line_1', $data)) {
            $store->setAddressLine1($data['address_line_1']);
        }
        if (array_key_exists('address_line_2', $data)) {
            $store->setAddressLine2($data['address_line_2']);
        }
        if (array_key_exists('city', $data)) {
            $store->setCity($data['city']);
        }
        if (array_key_exists('postal_code', $data)) {
            $store->setPostalCode($data['postal_code']);
        }
        if (array_key_exists('latitude', $data)) {
            $store->setLatitude($data['latitude']);
        }
        if (array_key_exists('longitude', $data)) {
            $store->setLongitude($data['longitude']);
        }

        $this->entityManager->persist($store);

        // Create version record.
        $version = new StoreVersion();
        $version->setStore($store);
        $version->setVersionNumber(1);
        $version->setChangeType('CREATED');
        $version->setSnapshot($this->snapshotStore($store));
        $version->setChangedBy($actor);
        $version->setChangeReason($changeReason);

        $this->entityManager->persist($version);

        $this->auditService->record(
            AuditAction::STORE_CREATED->value,
            'Store',
            $store->getId()->toRfc4122(),
            null,
            $this->snapshotStore($store),
            $actor,
        );

        $this->entityManager->flush();

        return $store;
    }

    public function update(Store $store, array $data, User $actor): Store
    {
        $oldSnapshot = $this->snapshotStore($store);
        $changeReason = $data['change_reason'] ?? null;

        if (array_key_exists('code', $data)) {
            $code = $data['code'];
            if (!is_string($code) || !preg_match('/^[A-Z0-9_\-]{3,20}$/', $code)) {
                throw new \InvalidArgumentException('Store code must match ^[A-Z0-9_\\-]{3,20}$.');
            }
            $existing = $this->storeRepository->findOneBy(['code' => $code]);
            if ($existing !== null && $existing->getId()->toRfc4122() !== $store->getId()->toRfc4122()) {
                throw new \InvalidArgumentException('Store code is already in use.');
            }
            $store->setCode($code);
        }

        if (array_key_exists('name', $data)) {
            $name = $data['name'];
            if (!is_string($name) || mb_strlen($name) < 1 || mb_strlen($name) > 150) {
                throw new \InvalidArgumentException('Store name must be between 1 and 150 characters.');
            }
            $store->setName($name);
        }

        if (array_key_exists('store_type', $data)) {
            $storeTypeEnum = StoreType::tryFrom($data['store_type'] ?? '');
            if ($storeTypeEnum === null) {
                $allowed = implode(', ', array_map(static fn (StoreType $t) => $t->value, StoreType::cases()));
                throw new \InvalidArgumentException(sprintf('Store type must be one of: %s.', $allowed));
            }
            $store->setStoreType($storeTypeEnum);
        }

        if (array_key_exists('region_id', $data)) {
            $regionId = $data['region_id'];
            if ($regionId === null || $regionId === '') {
                throw new \InvalidArgumentException('Region is required.');
            }
            $region = $this->regionRepository->find($regionId);
            if ($region === null) {
                throw new \InvalidArgumentException('Region not found.');
            }
            if (!$region->isActive()) {
                throw new \InvalidArgumentException('Region is not active.');
            }
            $store->setRegion($region);
        }

        if (array_key_exists('status', $data) && is_string($data['status'])) {
            $store->setStatus($data['status']);
        }
        if (array_key_exists('timezone', $data) && is_string($data['timezone'])) {
            $store->setTimezone($data['timezone']);
        }
        if (array_key_exists('address_line_1', $data)) {
            $store->setAddressLine1($data['address_line_1']);
        }
        if (array_key_exists('address_line_2', $data)) {
            $store->setAddressLine2($data['address_line_2']);
        }
        if (array_key_exists('city', $data)) {
            $store->setCity($data['city']);
        }
        if (array_key_exists('postal_code', $data)) {
            $store->setPostalCode($data['postal_code']);
        }
        if (array_key_exists('latitude', $data)) {
            $store->setLatitude($data['latitude']);
        }
        if (array_key_exists('longitude', $data)) {
            $store->setLongitude($data['longitude']);
        }
        if (array_key_exists('is_active', $data)) {
            $store->setIsActive((bool) $data['is_active']);
        }

        $store->setUpdatedAt(new \DateTimeImmutable());

        // Determine next version number.
        $latestVersion = $this->versionRepository->findOneBy(
            ['store' => $store],
            ['versionNumber' => 'DESC'],
        );
        $nextVersionNumber = $latestVersion !== null ? $latestVersion->getVersionNumber() + 1 : 1;

        $version = new StoreVersion();
        $version->setStore($store);
        $version->setVersionNumber($nextVersionNumber);
        $version->setChangeType('UPDATED');
        $version->setSnapshot($this->snapshotStore($store));
        $version->setChangedBy($actor);
        $version->setChangeReason($changeReason);

        $this->entityManager->persist($version);

        $this->auditService->record(
            AuditAction::STORE_UPDATED->value,
            'Store',
            $store->getId()->toRfc4122(),
            $oldSnapshot,
            $this->snapshotStore($store),
            $actor,
        );

        $this->entityManager->flush();

        return $store;
    }

    /**
     * @param string[]|null $accessibleStoreIds null = unrestricted (GLOBAL scope)
     * @return array{items: list<Store>, total: int}
     */
    public function list(
        int $page,
        int $perPage,
        ?string $regionId = null,
        ?string $type = null,
        ?string $status = null,
        ?array $accessibleStoreIds = null,
    ): array {
        $qb = $this->storeRepository->createQueryBuilder('s');

        if ($regionId !== null) {
            $qb->andWhere('s.region = :regionId')->setParameter('regionId', $regionId);
        }

        if ($type !== null) {
            $qb->andWhere('s.storeType = :type')->setParameter('type', $type);
        }

        if ($status !== null) {
            $qb->andWhere('s.status = :status')->setParameter('status', $status);
        }

        // Scope filtering: restrict to authorized stores.
        if ($accessibleStoreIds !== null) {
            if (\count($accessibleStoreIds) === 0) {
                return ['items' => [], 'total' => 0];
            }
            $qb->andWhere('s.id IN (:accessibleIds)')
                ->setParameter('accessibleIds', $accessibleStoreIds);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->orderBy('s.code', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function findById(string $id): ?Store
    {
        return $this->storeRepository->find($id);
    }

    /**
     * @return list<StoreVersion>
     */
    public function getVersionHistory(Store $store): array
    {
        return $this->versionRepository->findBy(
            ['store' => $store],
            ['versionNumber' => 'ASC'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotStore(Store $store): array
    {
        return [
            'id' => $store->getId()->toRfc4122(),
            'code' => $store->getCode(),
            'name' => $store->getName(),
            'store_type' => $store->getStoreType()->value,
            'status' => $store->getStatus(),
            'region_id' => $store->getRegion()->getId()->toRfc4122(),
            'timezone' => $store->getTimezone(),
            'address_line_1' => $store->getAddressLine1(),
            'address_line_2' => $store->getAddressLine2(),
            'city' => $store->getCity(),
            'postal_code' => $store->getPostalCode(),
            'latitude' => $store->getLatitude(),
            'longitude' => $store->getLongitude(),
            'is_active' => $store->isActive(),
        ];
    }
}
