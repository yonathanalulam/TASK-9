<?php

declare(strict_types=1);

namespace App\Service\DeliveryZone;

use App\Entity\DeliveryZone;
use App\Entity\DeliveryZoneVersion;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Repository\DeliveryZoneRepository;
use App\Repository\DeliveryZoneVersionRepository;
use App\Repository\StoreRepository;
use App\Service\Audit\AuditService;
use Doctrine\ORM\EntityManagerInterface;

class DeliveryZoneService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DeliveryZoneRepository $zoneRepository,
        private readonly DeliveryZoneVersionRepository $versionRepository,
        private readonly StoreRepository $storeRepository,
        private readonly AuditService $auditService,
    ) {
    }

    public function create(array $data, string $storeId, User $actor): DeliveryZone
    {
        $name = $data['name'] ?? '';
        $changeReason = $data['change_reason'] ?? null;

        // Validate store exists.
        $store = $this->storeRepository->find($storeId);
        if ($store === null) {
            throw new \InvalidArgumentException('Store not found.');
        }

        // Validate name.
        if (!is_string($name) || mb_strlen($name) < 1 || mb_strlen($name) > 120) {
            throw new \InvalidArgumentException('Zone name must be between 1 and 120 characters.');
        }

        // Validate name uniqueness per store among active zones.
        $existingZone = $this->zoneRepository->findOneBy([
            'store' => $store,
            'name' => $name,
            'isActive' => true,
        ]);
        if ($existingZone !== null) {
            throw new \InvalidArgumentException('An active zone with this name already exists for the store.');
        }

        // Validate minOrderThreshold.
        $minOrderThreshold = $data['min_order_threshold'] ?? 25.00;
        $minOrderThreshold = (float) $minOrderThreshold;
        if ($minOrderThreshold < 0 || $minOrderThreshold > 9999.99) {
            throw new \InvalidArgumentException('Minimum order threshold must be between 0 and 9999.99.');
        }

        // Validate deliveryFee.
        $deliveryFee = $data['delivery_fee'] ?? 3.99;
        $deliveryFee = (float) $deliveryFee;
        if ($deliveryFee < 0 || $deliveryFee > 999.99) {
            throw new \InvalidArgumentException('Delivery fee must be between 0 and 999.99.');
        }

        $zone = new DeliveryZone();
        $zone->setStore($store);
        $zone->setName($name);
        $zone->setMinOrderThreshold(number_format($minOrderThreshold, 2, '.', ''));
        $zone->setDeliveryFee(number_format($deliveryFee, 2, '.', ''));

        if (array_key_exists('status', $data) && is_string($data['status'])) {
            $zone->setStatus($data['status']);
        }

        $this->entityManager->persist($zone);

        // Create version record.
        $version = new DeliveryZoneVersion();
        $version->setZone($zone);
        $version->setVersionNumber(1);
        $version->setChangeType('CREATED');
        $version->setSnapshot($this->snapshotZone($zone));
        $version->setChangedBy($actor);
        $version->setChangeReason($changeReason);

        $this->entityManager->persist($version);

        $this->auditService->record(
            AuditAction::ZONE_CREATED->value,
            'DeliveryZone',
            $zone->getId()->toRfc4122(),
            null,
            $this->snapshotZone($zone),
            $actor,
        );

        $this->entityManager->flush();

        return $zone;
    }

    public function update(DeliveryZone $zone, array $data, User $actor): DeliveryZone
    {
        $oldSnapshot = $this->snapshotZone($zone);
        $changeReason = $data['change_reason'] ?? null;

        if (array_key_exists('name', $data)) {
            $name = $data['name'];
            if (!is_string($name) || mb_strlen($name) < 1 || mb_strlen($name) > 120) {
                throw new \InvalidArgumentException('Zone name must be between 1 and 120 characters.');
            }

            // Validate name uniqueness per store among active zones (excluding self).
            $existingZone = $this->zoneRepository->findOneBy([
                'store' => $zone->getStore(),
                'name' => $name,
                'isActive' => true,
            ]);
            if ($existingZone !== null && $existingZone->getId()->toRfc4122() !== $zone->getId()->toRfc4122()) {
                throw new \InvalidArgumentException('An active zone with this name already exists for the store.');
            }

            $zone->setName($name);
        }

        if (array_key_exists('min_order_threshold', $data)) {
            $minOrderThreshold = (float) $data['min_order_threshold'];
            if ($minOrderThreshold < 0 || $minOrderThreshold > 9999.99) {
                throw new \InvalidArgumentException('Minimum order threshold must be between 0 and 9999.99.');
            }
            $zone->setMinOrderThreshold(number_format($minOrderThreshold, 2, '.', ''));
        }

        if (array_key_exists('delivery_fee', $data)) {
            $deliveryFee = (float) $data['delivery_fee'];
            if ($deliveryFee < 0 || $deliveryFee > 999.99) {
                throw new \InvalidArgumentException('Delivery fee must be between 0 and 999.99.');
            }
            $zone->setDeliveryFee(number_format($deliveryFee, 2, '.', ''));
        }

        if (array_key_exists('status', $data) && is_string($data['status'])) {
            $zone->setStatus($data['status']);
        }

        if (array_key_exists('is_active', $data)) {
            $zone->setIsActive((bool) $data['is_active']);
        }

        $zone->setUpdatedAt(new \DateTimeImmutable());

        // Determine next version number.
        $latestVersion = $this->versionRepository->findOneBy(
            ['zone' => $zone],
            ['versionNumber' => 'DESC'],
        );
        $nextVersionNumber = $latestVersion !== null ? $latestVersion->getVersionNumber() + 1 : 1;

        $version = new DeliveryZoneVersion();
        $version->setZone($zone);
        $version->setVersionNumber($nextVersionNumber);
        $version->setChangeType('UPDATED');
        $version->setSnapshot($this->snapshotZone($zone));
        $version->setChangedBy($actor);
        $version->setChangeReason($changeReason);

        $this->entityManager->persist($version);

        $this->auditService->record(
            AuditAction::ZONE_UPDATED->value,
            'DeliveryZone',
            $zone->getId()->toRfc4122(),
            $oldSnapshot,
            $this->snapshotZone($zone),
            $actor,
        );

        $this->entityManager->flush();

        return $zone;
    }

    /**
     * @return array{items: list<DeliveryZone>, total: int}
     */
    public function list(string $storeId, int $page, int $perPage): array
    {
        $qb = $this->zoneRepository->createQueryBuilder('z')
            ->andWhere('z.store = :storeId')
            ->setParameter('storeId', $storeId);

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(z.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->orderBy('z.name', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function findById(string $id): ?DeliveryZone
    {
        return $this->zoneRepository->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotZone(DeliveryZone $zone): array
    {
        return [
            'id' => $zone->getId()->toRfc4122(),
            'store_id' => $zone->getStore()->getId()->toRfc4122(),
            'name' => $zone->getName(),
            'status' => $zone->getStatus(),
            'min_order_threshold' => $zone->getMinOrderThreshold(),
            'delivery_fee' => $zone->getDeliveryFee(),
            'is_active' => $zone->isActive(),
        ];
    }
}
