<?php

declare(strict_types=1);

namespace App\Service\DeliveryZone;

use App\Entity\DeliveryWindow;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Repository\DeliveryWindowRepository;
use App\Repository\DeliveryZoneRepository;
use App\Service\Audit\AuditService;
use Doctrine\ORM\EntityManagerInterface;

class DeliveryWindowService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DeliveryWindowRepository $windowRepository,
        private readonly DeliveryZoneRepository $zoneRepository,
        private readonly AuditService $auditService,
    ) {
    }

    public function create(array $data, string $zoneId, User $actor): DeliveryWindow
    {
        $zone = $this->zoneRepository->find($zoneId);
        if ($zone === null) {
            throw new \InvalidArgumentException('Delivery zone not found.');
        }

        $dayOfWeek = $data['day_of_week'] ?? null;
        $startTimeStr = $data['start_time'] ?? null;
        $endTimeStr = $data['end_time'] ?? null;

        // Validate dayOfWeek.
        if ($dayOfWeek === null || !is_numeric($dayOfWeek) || (int) $dayOfWeek < 0 || (int) $dayOfWeek > 6) {
            throw new \InvalidArgumentException('Day of week must be an integer between 0 and 6.');
        }
        $dayOfWeek = (int) $dayOfWeek;

        // Parse and validate times.
        if (!is_string($startTimeStr) || $startTimeStr === '') {
            throw new \InvalidArgumentException('Start time is required.');
        }
        if (!is_string($endTimeStr) || $endTimeStr === '') {
            throw new \InvalidArgumentException('End time is required.');
        }

        $startTime = \DateTimeImmutable::createFromFormat('H:i', $startTimeStr);
        $endTime = \DateTimeImmutable::createFromFormat('H:i', $endTimeStr);

        if ($startTime === false) {
            throw new \InvalidArgumentException('Start time must be in HH:MM format.');
        }
        if ($endTime === false) {
            throw new \InvalidArgumentException('End time must be in HH:MM format.');
        }

        // Normalize to consistent date for comparison.
        $startTime = $startTime->setDate(2000, 1, 1);
        $endTime = $endTime->setDate(2000, 1, 1);

        if ($startTime >= $endTime) {
            throw new \InvalidArgumentException('Start time must be before end time.');
        }

        // Validate no overlap with existing active windows on same zone + day.
        $this->assertNoOverlap($zoneId, $dayOfWeek, $startTime, $endTime);

        $window = new DeliveryWindow();
        $window->setZone($zone);
        $window->setDayOfWeek($dayOfWeek);
        $window->setStartTime($startTime);
        $window->setEndTime($endTime);
        $window->setIsActive(true);

        $this->entityManager->persist($window);

        $this->auditService->record(
            AuditAction::WINDOW_CREATED->value,
            'DeliveryWindow',
            $window->getId()->toRfc4122(),
            null,
            $this->snapshotWindow($window),
            $actor,
        );

        $this->entityManager->flush();

        return $window;
    }

    public function update(DeliveryWindow $window, array $data, User $actor): DeliveryWindow
    {
        $oldSnapshot = $this->snapshotWindow($window);

        $dayOfWeek = $window->getDayOfWeek();
        $startTime = $window->getStartTime();
        $endTime = $window->getEndTime();

        if (array_key_exists('day_of_week', $data)) {
            if (!is_numeric($data['day_of_week']) || (int) $data['day_of_week'] < 0 || (int) $data['day_of_week'] > 6) {
                throw new \InvalidArgumentException('Day of week must be an integer between 0 and 6.');
            }
            $dayOfWeek = (int) $data['day_of_week'];
            $window->setDayOfWeek($dayOfWeek);
        }

        if (array_key_exists('start_time', $data)) {
            $parsed = \DateTimeImmutable::createFromFormat('H:i', $data['start_time']);
            if ($parsed === false) {
                throw new \InvalidArgumentException('Start time must be in HH:MM format.');
            }
            $startTime = $parsed->setDate(2000, 1, 1);
            $window->setStartTime($startTime);
        }

        if (array_key_exists('end_time', $data)) {
            $parsed = \DateTimeImmutable::createFromFormat('H:i', $data['end_time']);
            if ($parsed === false) {
                throw new \InvalidArgumentException('End time must be in HH:MM format.');
            }
            $endTime = $parsed->setDate(2000, 1, 1);
            $window->setEndTime($endTime);
        }

        // Normalize dates for comparison.
        $startTime = $startTime->setDate(2000, 1, 1);
        $endTime = $endTime->setDate(2000, 1, 1);

        if ($startTime >= $endTime) {
            throw new \InvalidArgumentException('Start time must be before end time.');
        }

        // Validate no overlap excluding self.
        $this->assertNoOverlap(
            $window->getZone()->getId()->toRfc4122(),
            $dayOfWeek,
            $startTime,
            $endTime,
            $window->getId()->toRfc4122(),
        );

        if (array_key_exists('is_active', $data)) {
            $window->setIsActive((bool) $data['is_active']);
        }

        $window->setUpdatedAt(new \DateTimeImmutable());

        $this->auditService->record(
            AuditAction::WINDOW_UPDATED->value,
            'DeliveryWindow',
            $window->getId()->toRfc4122(),
            $oldSnapshot,
            $this->snapshotWindow($window),
            $actor,
        );

        $this->entityManager->flush();

        return $window;
    }

    public function deactivate(DeliveryWindow $window, User $actor): void
    {
        $oldSnapshot = $this->snapshotWindow($window);

        $window->setIsActive(false);
        $window->setUpdatedAt(new \DateTimeImmutable());

        $this->auditService->record(
            AuditAction::WINDOW_DEACTIVATED->value,
            'DeliveryWindow',
            $window->getId()->toRfc4122(),
            $oldSnapshot,
            $this->snapshotWindow($window),
            $actor,
        );

        $this->entityManager->flush();
    }

    /**
     * @return list<DeliveryWindow>
     */
    public function listForZone(string $zoneId): array
    {
        return $this->windowRepository->findBy(
            ['zone' => $zoneId],
            ['dayOfWeek' => 'ASC', 'startTime' => 'ASC'],
        );
    }

    /**
     * Check that the proposed window does not overlap with any existing active
     * window on the same zone and day of week.
     *
     * Overlap condition: existing.start < proposed.end AND existing.end > proposed.start
     */
    private function assertNoOverlap(
        string $zoneId,
        int $dayOfWeek,
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
        ?string $excludeWindowId = null,
    ): void {
        $qb = $this->windowRepository->createQueryBuilder('w')
            ->andWhere('w.zone = :zoneId')
            ->andWhere('w.dayOfWeek = :dayOfWeek')
            ->andWhere('w.isActive = :active')
            ->andWhere('w.startTime < :endTime')
            ->andWhere('w.endTime > :startTime')
            ->setParameter('zoneId', $zoneId)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->setParameter('active', true)
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime);

        if ($excludeWindowId !== null) {
            $qb->andWhere('w.id != :excludeId')
                ->setParameter('excludeId', $excludeWindowId);
        }

        $overlapping = $qb->getQuery()->getResult();

        if (count($overlapping) > 0) {
            throw new \InvalidArgumentException('The proposed window overlaps with an existing active window.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotWindow(DeliveryWindow $window): array
    {
        return [
            'id' => $window->getId()->toRfc4122(),
            'zone_id' => $window->getZone()->getId()->toRfc4122(),
            'day_of_week' => $window->getDayOfWeek(),
            'start_time' => $window->getStartTime()->format('H:i'),
            'end_time' => $window->getEndTime()->format('H:i'),
            'is_active' => $window->isActive(),
        ];
    }
}
