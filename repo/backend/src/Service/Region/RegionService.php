<?php

declare(strict_types=1);

namespace App\Service\Region;

use App\Entity\MdmRegion;
use App\Entity\MdmRegionVersion;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Repository\MdmRegionRepository;
use App\Repository\MdmRegionVersionRepository;
use App\Service\Audit\AuditService;
use Doctrine\ORM\EntityManagerInterface;

class RegionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MdmRegionRepository $regionRepository,
        private readonly MdmRegionVersionRepository $versionRepository,
        private readonly AuditService $auditService,
    ) {
    }

    public function create(array $data, User $actor): MdmRegion
    {
        $code = $data['code'] ?? '';
        $name = $data['name'] ?? '';
        $parentId = $data['parent_id'] ?? null;
        $effectiveFrom = $data['effective_from'] ?? null;
        $changeReason = $data['change_reason'] ?? null;

        // Validate code format.
        if (!is_string($code) || !preg_match('/^[A-Z]{2,5}$/', $code)) {
            throw new \InvalidArgumentException('Region code must match ^[A-Z]{2,5}$.');
        }

        // Validate code uniqueness across ALL regions (not just active).
        $existing = $this->regionRepository->findOneBy(['code' => $code]);
        if ($existing !== null) {
            throw new \InvalidArgumentException('Region code is already in use.');
        }

        // Validate name.
        if (!is_string($name) || mb_strlen($name) < 1 || mb_strlen($name) > 150) {
            throw new \InvalidArgumentException('Region name must be between 1 and 150 characters.');
        }

        // Resolve parent if provided.
        $parent = null;
        if ($parentId !== null && $parentId !== '') {
            $parent = $this->regionRepository->find($parentId);
            if ($parent === null) {
                throw new \InvalidArgumentException('Parent region not found.');
            }
        }

        // Parse effectiveFrom.
        $effectiveFromDate = $effectiveFrom !== null
            ? new \DateTimeImmutable($effectiveFrom)
            : new \DateTimeImmutable('today');

        // Validate child effectiveFrom >= parent effectiveFrom.
        if ($parent !== null && $effectiveFromDate < $parent->getEffectiveFrom()) {
            throw new \InvalidArgumentException('Child effectiveFrom must be on or after parent effectiveFrom.');
        }

        // Compute hierarchy level.
        $hierarchyLevel = $parent !== null ? $parent->getHierarchyLevel() + 1 : 0;

        $region = new MdmRegion();
        $region->setCode($code);
        $region->setName($name);
        $region->setParent($parent);
        $region->setHierarchyLevel($hierarchyLevel);
        $region->setEffectiveFrom($effectiveFromDate);
        $region->setIsActive(true);

        $this->entityManager->persist($region);

        // Create version record.
        $version = new MdmRegionVersion();
        $version->setRegion($region);
        $version->setVersionNumber(1);
        $version->setChangeType('CREATED');
        $version->setSnapshot($this->snapshotRegion($region));
        $version->setChangedBy($actor);
        $version->setChangeReason($changeReason);

        $this->entityManager->persist($version);

        // Record audit event.
        $this->auditService->record(
            AuditAction::REGION_CREATED->value,
            'MdmRegion',
            $region->getId()->toRfc4122(),
            null,
            $this->snapshotRegion($region),
            $actor,
        );

        $this->entityManager->flush();

        return $region;
    }

    public function update(MdmRegion $region, array $data, User $actor): MdmRegion
    {
        $oldSnapshot = $this->snapshotRegion($region);
        $changeReason = $data['change_reason'] ?? null;

        if (array_key_exists('code', $data)) {
            $code = $data['code'];
            if (!is_string($code) || !preg_match('/^[A-Z]{2,5}$/', $code)) {
                throw new \InvalidArgumentException('Region code must match ^[A-Z]{2,5}$.');
            }
            $existing = $this->regionRepository->findOneBy(['code' => $code]);
            if ($existing !== null && $existing->getId()->toRfc4122() !== $region->getId()->toRfc4122()) {
                throw new \InvalidArgumentException('Region code is already in use.');
            }
            $region->setCode($code);
        }

        if (array_key_exists('name', $data)) {
            $name = $data['name'];
            if (!is_string($name) || mb_strlen($name) < 1 || mb_strlen($name) > 150) {
                throw new \InvalidArgumentException('Region name must be between 1 and 150 characters.');
            }
            $region->setName($name);
        }

        if (array_key_exists('parent_id', $data)) {
            $parentId = $data['parent_id'];
            $parent = null;
            if ($parentId !== null && $parentId !== '') {
                $parent = $this->regionRepository->find($parentId);
                if ($parent === null) {
                    throw new \InvalidArgumentException('Parent region not found.');
                }
            }
            $region->setParent($parent);
            $region->setHierarchyLevel($parent !== null ? $parent->getHierarchyLevel() + 1 : 0);
        }

        if (array_key_exists('effective_from', $data)) {
            $effectiveFromDate = new \DateTimeImmutable($data['effective_from']);
            $parent = $region->getParent();
            if ($parent !== null && $effectiveFromDate < $parent->getEffectiveFrom()) {
                throw new \InvalidArgumentException('Child effectiveFrom must be on or after parent effectiveFrom.');
            }
            $region->setEffectiveFrom($effectiveFromDate);
        }

        if (array_key_exists('effective_until', $data)) {
            $region->setEffectiveUntil(
                $data['effective_until'] !== null ? new \DateTimeImmutable($data['effective_until']) : null,
            );
        }

        if (array_key_exists('is_active', $data)) {
            $region->setIsActive((bool) $data['is_active']);
        }

        $region->setUpdatedAt(new \DateTimeImmutable());

        // Determine next version number.
        $latestVersion = $this->versionRepository->findOneBy(
            ['region' => $region],
            ['versionNumber' => 'DESC'],
        );
        $nextVersionNumber = $latestVersion !== null ? $latestVersion->getVersionNumber() + 1 : 1;

        $version = new MdmRegionVersion();
        $version->setRegion($region);
        $version->setVersionNumber($nextVersionNumber);
        $version->setChangeType('UPDATED');
        $version->setSnapshot($this->snapshotRegion($region));
        $version->setChangedBy($actor);
        $version->setChangeReason($changeReason);

        $this->entityManager->persist($version);

        $this->auditService->record(
            AuditAction::REGION_UPDATED->value,
            'MdmRegion',
            $region->getId()->toRfc4122(),
            $oldSnapshot,
            $this->snapshotRegion($region),
            $actor,
        );

        $this->entityManager->flush();

        return $region;
    }

    public function close(MdmRegion $region, array $childReassignments, User $actor): void
    {
        // Gather all active children.
        $activeChildren = $this->regionRepository->findBy([
            'parent' => $region,
            'isActive' => true,
        ]);

        // Validate that every active child is accounted for in reassignments.
        foreach ($activeChildren as $child) {
            $childIdStr = $child->getId()->toRfc4122();
            if (!array_key_exists($childIdStr, $childReassignments)) {
                throw new \InvalidArgumentException(
                    sprintf('Active child region %s must be reassigned.', $childIdStr),
                );
            }
        }

        // Reassign children in the same transaction.
        foreach ($activeChildren as $child) {
            $childIdStr = $child->getId()->toRfc4122();
            $newParentId = $childReassignments[$childIdStr];

            $newParent = $this->regionRepository->find($newParentId);
            if ($newParent === null) {
                throw new \InvalidArgumentException(
                    sprintf('New parent region %s not found for child %s.', $newParentId, $childIdStr),
                );
            }

            $oldChildSnapshot = $this->snapshotRegion($child);
            $child->setParent($newParent);
            $child->setHierarchyLevel($newParent->getHierarchyLevel() + 1);
            $child->setUpdatedAt(new \DateTimeImmutable());

            // Version the child reassignment.
            $latestChildVersion = $this->versionRepository->findOneBy(
                ['region' => $child],
                ['versionNumber' => 'DESC'],
            );
            $nextChildVersion = $latestChildVersion !== null ? $latestChildVersion->getVersionNumber() + 1 : 1;

            $childVersionRecord = new MdmRegionVersion();
            $childVersionRecord->setRegion($child);
            $childVersionRecord->setVersionNumber($nextChildVersion);
            $childVersionRecord->setChangeType('UPDATED');
            $childVersionRecord->setSnapshot($this->snapshotRegion($child));
            $childVersionRecord->setChangedBy($actor);
            $childVersionRecord->setChangeReason('Parent region closed; reassigned.');

            $this->entityManager->persist($childVersionRecord);

            $this->auditService->record(
                AuditAction::REGION_UPDATED->value,
                'MdmRegion',
                $childIdStr,
                $oldChildSnapshot,
                $this->snapshotRegion($child),
                $actor,
            );
        }

        // Close the region itself.
        $oldSnapshot = $this->snapshotRegion($region);
        $region->setEffectiveUntil(new \DateTimeImmutable('today'));
        $region->setIsActive(false);
        $region->setUpdatedAt(new \DateTimeImmutable());

        $latestVersion = $this->versionRepository->findOneBy(
            ['region' => $region],
            ['versionNumber' => 'DESC'],
        );
        $nextVersionNumber = $latestVersion !== null ? $latestVersion->getVersionNumber() + 1 : 1;

        $versionRecord = new MdmRegionVersion();
        $versionRecord->setRegion($region);
        $versionRecord->setVersionNumber($nextVersionNumber);
        $versionRecord->setChangeType('CLOSED');
        $versionRecord->setSnapshot($this->snapshotRegion($region));
        $versionRecord->setChangedBy($actor);
        $versionRecord->setChangeReason('Region closed.');

        $this->entityManager->persist($versionRecord);

        $this->auditService->record(
            AuditAction::REGION_CLOSED->value,
            'MdmRegion',
            $region->getId()->toRfc4122(),
            $oldSnapshot,
            $this->snapshotRegion($region),
            $actor,
        );

        $this->entityManager->flush();
    }

    /**
     * @return array{items: list<MdmRegion>, total: int}
     */
    /**
     * @param list<string>|null $accessibleRegionIds RFC4122 UUIDs of regions the actor can access.
     *                                               null = global access (no filtering).
     */
    public function list(int $page, int $perPage, ?bool $activeOnly = null, ?string $parentId = null, ?array $accessibleRegionIds = null): array
    {
        $qb = $this->regionRepository->createQueryBuilder('r');

        if ($activeOnly !== null) {
            $qb->andWhere('r.isActive = :active')->setParameter('active', $activeOnly);
        }

        if ($parentId !== null) {
            $qb->andWhere('r.parent = :parentId')->setParameter('parentId', $parentId);
        }

        // Scope filtering: restrict to accessible regions when actor is not global.
        if ($accessibleRegionIds !== null) {
            if (\count($accessibleRegionIds) === 0) {
                return ['items' => [], 'total' => 0];
            }
            $regionBinIds = array_map(
                static fn (string $id) => \Symfony\Component\Uid\Uuid::fromString($id)->toBinary(),
                $accessibleRegionIds,
            );
            $qb->andWhere('r.id IN (:regionIds)')->setParameter('regionIds', $regionBinIds);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->orderBy('r.code', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function findById(string $id): ?MdmRegion
    {
        return $this->regionRepository->find($id);
    }

    /**
     * @return list<MdmRegionVersion>
     */
    public function getVersionHistory(MdmRegion $region): array
    {
        return $this->versionRepository->findBy(
            ['region' => $region],
            ['versionNumber' => 'ASC'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotRegion(MdmRegion $region): array
    {
        return [
            'id' => $region->getId()->toRfc4122(),
            'code' => $region->getCode(),
            'name' => $region->getName(),
            'parent_id' => $region->getParent()?->getId()->toRfc4122(),
            'hierarchy_level' => $region->getHierarchyLevel(),
            'effective_from' => $region->getEffectiveFrom()->format('Y-m-d'),
            'effective_until' => $region->getEffectiveUntil()?->format('Y-m-d'),
            'is_active' => $region->isActive(),
        ];
    }
}
