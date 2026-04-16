<?php

declare(strict_types=1);

namespace App\Service\Authorization;

use App\Entity\DeliveryZone;
use App\Entity\MdmRegion;
use App\Entity\Store;
use App\Entity\User;
use App\Enum\ScopeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class ScopeResolver
{
    public function __construct(
        private readonly RbacService $rbacService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Returns true if the user has any effective assignment granting access to the given store.
     *
     * Access is granted when at least one assignment satisfies:
     *  - GLOBAL scope, OR
     *  - REGION scope whose scopeId matches the store's region, OR
     *  - STORE scope whose scopeId matches the store itself.
     */
    public function canAccessStore(User $user, Store $store): bool
    {
        $assignments = $this->rbacService->getEffectiveAssignments($user);

        $storeIdBinary = $store->getId()->toRfc4122();
        $regionIdBinary = $store->getRegion()->getId()->toRfc4122();

        foreach ($assignments as $assignment) {
            if ($assignment->getScopeType() === ScopeType::GLOBAL) {
                return true;
            }

            if (
                $assignment->getScopeType() === ScopeType::REGION
                && $assignment->getScopeId() === $regionIdBinary
            ) {
                return true;
            }

            if (
                $assignment->getScopeType() === ScopeType::STORE
                && $assignment->getScopeId() === $storeIdBinary
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true if the user has any effective assignment granting access to the given region.
     *
     * Access is granted when at least one assignment satisfies:
     *  - GLOBAL scope, OR
     *  - REGION scope whose scopeId matches the region.
     */
    public function canAccessRegion(User $user, MdmRegion $region): bool
    {
        $assignments = $this->rbacService->getEffectiveAssignments($user);

        $regionIdBinary = $region->getId()->toRfc4122();

        foreach ($assignments as $assignment) {
            if ($assignment->getScopeType() === ScopeType::GLOBAL) {
                return true;
            }

            if (
                $assignment->getScopeType() === ScopeType::REGION
                && $assignment->getScopeId() === $regionIdBinary
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delegates to canAccessStore using the zone's parent store.
     */
    public function canAccessDeliveryZone(User $user, DeliveryZone $zone): bool
    {
        return $this->canAccessStore($user, $zone->getStore());
    }

    /**
     * Returns the list of store UUIDs the user can access, or null if the user
     * has GLOBAL scope (meaning unrestricted access to all stores).
     *
     * For REGION-scoped assignments the method queries all stores within that region.
     *
     * @return Uuid[]|null
     */
    public function getAccessibleStoreIds(User $user): ?array
    {
        $assignments = $this->rbacService->getEffectiveAssignments($user);

        $storeIds = [];
        $regionIdsBinary = [];

        foreach ($assignments as $assignment) {
            if ($assignment->getScopeType() === ScopeType::GLOBAL) {
                return null; // unrestricted
            }

            if ($assignment->getScopeType() === ScopeType::STORE && $assignment->getScopeId() !== null) {
                $storeIds[] = Uuid::fromString($assignment->getScopeId());
            }

            if ($assignment->getScopeType() === ScopeType::REGION && $assignment->getScopeId() !== null) {
                $regionIdsBinary[] = $assignment->getScopeId();
            }
        }

        // Deduplicate region IDs before querying.
        $regionIdsBinary = array_unique($regionIdsBinary, \SORT_STRING);

        if ($regionIdsBinary !== []) {
            $regionUuids = array_map(
                static fn (string $id): Uuid => Uuid::fromString($id),
                $regionIdsBinary,
            );

            $qb = $this->entityManager->createQueryBuilder()
                ->select('s.id')
                ->from(Store::class, 's')
                ->join('s.region', 'r')
                ->where('r.id IN (:regionIds)')
                ->setParameter('regionIds', $regionUuids);

            $rows = $qb->getQuery()->getScalarResult();

            foreach ($rows as $row) {
                $storeIds[] = $row['id'] instanceof Uuid ? $row['id'] : Uuid::fromString((string) $row['id']);
            }
        }

        // Deduplicate by string representation.
        $seen = [];
        $unique = [];
        foreach ($storeIds as $uuid) {
            $key = $uuid->toRfc4122();
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $uuid;
            }
        }

        return $unique;
    }

    /**
     * Returns the list of region UUIDs the user can access, or null if the user
     * has GLOBAL scope (meaning unrestricted access to all regions).
     *
     * @return Uuid[]|null
     */
    public function getAccessibleRegionIds(User $user): ?array
    {
        $assignments = $this->rbacService->getEffectiveAssignments($user);

        $regionIds = [];

        foreach ($assignments as $assignment) {
            if ($assignment->getScopeType() === ScopeType::GLOBAL) {
                return null; // unrestricted
            }

            if ($assignment->getScopeType() === ScopeType::REGION && $assignment->getScopeId() !== null) {
                $regionIds[] = Uuid::fromString($assignment->getScopeId());
            }

            // STORE-scoped users get the region their store belongs to.
            if ($assignment->getScopeType() === ScopeType::STORE && $assignment->getScopeId() !== null) {
                $storeUuid = Uuid::fromString($assignment->getScopeId());
                $store = $this->entityManager->getRepository(\App\Entity\Store::class)->find($storeUuid);
                if ($store !== null) {
                    $regionIds[] = $store->getRegion()->getId();
                }
            }
        }

        // Deduplicate by RFC4122 string.
        $seen = [];
        $unique = [];
        foreach ($regionIds as $uuid) {
            $key = $uuid->toRfc4122();
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $uuid;
            }
        }

        return $unique;
    }
}
