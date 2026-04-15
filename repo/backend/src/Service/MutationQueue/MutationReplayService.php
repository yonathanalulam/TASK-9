<?php

declare(strict_types=1);

namespace App\Service\MutationQueue;

use App\Entity\MutationQueueLog;
use App\Entity\User;
use App\Repository\MutationQueueLogRepository;
use App\Service\Audit\AuditService;
use App\Enum\RoleName;
use App\Service\Authorization\RbacService;
use App\Service\Authorization\ScopeResolver;
use App\Service\Store\StoreService;
use App\Service\Region\RegionService;
use App\Service\DeliveryZone\DeliveryZoneService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MutationReplayService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MutationQueueLogRepository $mutationQueueLogRepository,
        private readonly AuditService $auditService,
        private readonly StoreService $storeService,
        private readonly RegionService $regionService,
        private readonly DeliveryZoneService $deliveryZoneService,
        private readonly ScopeResolver $scopeResolver,
        private readonly RbacService $rbacService,
    ) {
    }

    /**
     * Replay a batch of mutations from an offline client.
     *
     * Each mutation is processed idempotently: if a mutation_id already exists,
     * the existing result is returned instead of re-applying.
     *
     * @param array<int, array<string, mixed>> $mutations
     * @return array<int, array{mutation_id: string, status: string, detail?: string}>
     */
    public function replayBatch(array $mutations, User $actor): array
    {
        $results = [];

        foreach ($mutations as $mutation) {
            $mutationId = (string) ($mutation['mutation_id'] ?? '');
            $clientId = (string) ($mutation['client_id'] ?? '');
            $entityType = (string) ($mutation['entity_type'] ?? '');
            $entityId = isset($mutation['entity_id']) ? (string) $mutation['entity_id'] : null;
            $operation = strtoupper((string) ($mutation['operation'] ?? ''));
            $payload = is_array($mutation['payload'] ?? null) ? $mutation['payload'] : [];

            if ($mutationId === '') {
                $results[] = [
                    'mutation_id' => $mutationId,
                    'status' => MutationQueueLog::STATUS_REJECTED,
                    'detail' => 'mutation_id is required.',
                ];

                continue;
            }

            // Idempotency check
            $existing = $this->mutationQueueLogRepository->findByMutationId($mutationId);

            if ($existing !== null) {
                $result = [
                    'mutation_id' => $mutationId,
                    'status' => $existing->getStatus(),
                ];

                if ($existing->getConflictDetail() !== null) {
                    $result['detail'] = $existing->getConflictDetail();
                }

                $results[] = $result;

                continue;
            }

            // Create log entry
            $log = new MutationQueueLog();
            $log->setClientId($clientId);
            $log->setMutationId($mutationId);
            $log->setEntityType($entityType);
            $log->setEntityId($entityId);
            $log->setOperation($operation);
            $log->setPayload($payload);
            $log->setStatus(MutationQueueLog::STATUS_RECEIVED);

            $this->entityManager->persist($log);
            $this->entityManager->flush();

            try {
                $this->applyMutation($log, $actor);
                $log->setStatus(MutationQueueLog::STATUS_APPLIED);
                $log->setProcessedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                $results[] = [
                    'mutation_id' => $mutationId,
                    'status' => MutationQueueLog::STATUS_APPLIED,
                ];
            } catch (ConflictHttpException $e) {
                $log->setStatus(MutationQueueLog::STATUS_CONFLICT);
                $log->setConflictDetail($e->getMessage());
                $log->setProcessedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                $results[] = [
                    'mutation_id' => $mutationId,
                    'status' => MutationQueueLog::STATUS_CONFLICT,
                    'detail' => $e->getMessage(),
                ];
            } catch (\InvalidArgumentException $e) {
                $log->setStatus(MutationQueueLog::STATUS_REJECTED);
                $log->setConflictDetail($e->getMessage());
                $log->setProcessedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                $results[] = [
                    'mutation_id' => $mutationId,
                    'status' => MutationQueueLog::STATUS_REJECTED,
                    'detail' => $e->getMessage(),
                ];
            } catch (AccessDeniedHttpException $e) {
                $log->setStatus(MutationQueueLog::STATUS_REJECTED);
                $log->setConflictDetail($e->getMessage());
                $log->setProcessedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                $results[] = [
                    'mutation_id' => $mutationId,
                    'status' => MutationQueueLog::STATUS_REJECTED,
                    'detail' => $e->getMessage(),
                ];
            } catch (\Throwable $e) {
                $log->setStatus(MutationQueueLog::STATUS_REJECTED);
                $log->setConflictDetail('Internal error: ' . $e->getMessage());
                $log->setProcessedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                $results[] = [
                    'mutation_id' => $mutationId,
                    'status' => MutationQueueLog::STATUS_REJECTED,
                    'detail' => 'Internal error during mutation replay.',
                ];
            }
        }

        return $results;
    }

    private function applyMutation(MutationQueueLog $log, User $actor): void
    {
        $entityType = strtolower($log->getEntityType());
        $operation = $log->getOperation();
        $payload = $log->getPayload();
        $entityId = $log->getEntityId();

        match ($entityType) {
            'store' => $this->applyStoreMutation($operation, $entityId, $payload, $actor),
            'region', 'mdm_region' => $this->applyRegionMutation($operation, $entityId, $payload, $actor),
            'delivery_zone', 'zone' => $this->applyZoneMutation($operation, $entityId, $payload, $actor),
            default => throw new \InvalidArgumentException(sprintf(
                'Unsupported entity type for mutation replay: "%s".',
                $log->getEntityType(),
            )),
        };
    }

    private function applyStoreMutation(string $operation, ?string $entityId, array $payload, User $actor): void
    {
        // Store creation requires ADMINISTRATOR role (same as StoreController::create).
        if ($operation === MutationQueueLog::OPERATION_CREATE) {
            if (!$this->rbacService->hasAnyRole($actor, [RoleName::ADMINISTRATOR])) {
                throw new AccessDeniedHttpException('Store creation requires Administrator role.');
            }
        }

        match ($operation) {
            MutationQueueLog::OPERATION_CREATE => $this->storeService->create($payload, $actor),
            MutationQueueLog::OPERATION_UPDATE => $this->applyStoreUpdate($entityId, $payload, $actor),
            MutationQueueLog::OPERATION_DELETE => throw new \InvalidArgumentException('Store deletion is not supported.'),
            default => throw new \InvalidArgumentException(sprintf('Unsupported operation: "%s".', $operation)),
        };
    }

    private function applyStoreUpdate(?string $entityId, array $payload, User $actor): void
    {
        if ($entityId === null) {
            throw new \InvalidArgumentException('entity_id is required for UPDATE operations.');
        }

        // Role check: mirrors StoreVoter::canEdit() — STORE_MANAGER | ADMINISTRATOR.
        if (!$this->rbacService->hasAnyRole($actor, [RoleName::STORE_MANAGER, RoleName::ADMINISTRATOR])) {
            throw new AccessDeniedHttpException('Store editing requires Store Manager or Administrator role.');
        }

        $store = $this->storeService->findById($entityId);

        if ($store === null) {
            throw new \InvalidArgumentException(sprintf('Store "%s" not found.', $entityId));
        }

        // Scope check: mirrors StoreVoter::canEdit() — canAccessStore().
        if (!$this->scopeResolver->canAccessStore($actor, $store)) {
            throw new AccessDeniedHttpException(sprintf('Actor does not have scope access to store "%s".', $entityId));
        }

        $this->storeService->update($store, $payload, $actor);
    }

    private function applyRegionMutation(string $operation, ?string $entityId, array $payload, User $actor): void
    {
        // Region creation requires ADMINISTRATOR role (same as RegionController::create).
        if ($operation === MutationQueueLog::OPERATION_CREATE) {
            if (!$this->rbacService->hasAnyRole($actor, [RoleName::ADMINISTRATOR])) {
                throw new AccessDeniedHttpException('Region creation requires Administrator role.');
            }
        }

        match ($operation) {
            MutationQueueLog::OPERATION_CREATE => $this->regionService->create($payload, $actor),
            MutationQueueLog::OPERATION_UPDATE => $this->applyRegionUpdate($entityId, $payload, $actor),
            MutationQueueLog::OPERATION_DELETE => throw new \InvalidArgumentException('Region deletion is not supported.'),
            default => throw new \InvalidArgumentException(sprintf('Unsupported operation: "%s".', $operation)),
        };
    }

    private function applyRegionUpdate(?string $entityId, array $payload, User $actor): void
    {
        if ($entityId === null) {
            throw new \InvalidArgumentException('entity_id is required for UPDATE operations.');
        }

        // Region edit requires ADMINISTRATOR role (same as RegionController::update).
        if (!$this->rbacService->hasAnyRole($actor, [RoleName::ADMINISTRATOR])) {
            throw new AccessDeniedHttpException('Region editing requires Administrator role.');
        }

        $region = $this->regionService->findById($entityId);

        if ($region === null) {
            throw new \InvalidArgumentException(sprintf('Region "%s" not found.', $entityId));
        }

        // Per-entity scope check: verify actor can access this region.
        if (!$this->scopeResolver->canAccessRegion($actor, $region)) {
            throw new AccessDeniedHttpException(sprintf('Actor does not have scope access to region "%s".', $entityId));
        }

        $this->regionService->update($region, $payload, $actor);
    }

    private function applyZoneMutation(string $operation, ?string $entityId, array $payload, User $actor): void
    {
        match ($operation) {
            MutationQueueLog::OPERATION_CREATE => $this->applyZoneCreate($payload, $actor),
            MutationQueueLog::OPERATION_UPDATE => $this->applyZoneUpdate($entityId, $payload, $actor),
            MutationQueueLog::OPERATION_DELETE => throw new \InvalidArgumentException('Zone deletion is not supported.'),
            default => throw new \InvalidArgumentException(sprintf('Unsupported operation: "%s".', $operation)),
        };
    }

    private function applyZoneCreate(array $payload, User $actor): void
    {
        $storeId = $payload['store_id'] ?? null;

        if ($storeId === null || $storeId === '') {
            throw new \InvalidArgumentException('store_id is required in payload for zone creation.');
        }

        // Role check: mirrors DeliveryZoneVoter::canCreate() — STORE_MANAGER | DISPATCHER | ADMINISTRATOR.
        if (!$this->rbacService->hasAnyRole($actor, [RoleName::STORE_MANAGER, RoleName::DISPATCHER, RoleName::ADMINISTRATOR])) {
            throw new AccessDeniedHttpException('Zone creation requires Store Manager, Dispatcher, or Administrator role.');
        }

        // Scope check: mirrors DeliveryZoneVoter::canCreate(user, store) — canAccessStore().
        $store = $this->storeService->findById((string) $storeId);

        if ($store === null) {
            throw new \InvalidArgumentException(sprintf('Store "%s" not found.', $storeId));
        }

        if (!$this->scopeResolver->canAccessStore($actor, $store)) {
            throw new AccessDeniedHttpException(sprintf('Actor does not have scope access to store "%s".', $storeId));
        }

        $this->deliveryZoneService->create($payload, (string) $storeId, $actor);
    }

    private function applyZoneUpdate(?string $entityId, array $payload, User $actor): void
    {
        if ($entityId === null) {
            throw new \InvalidArgumentException('entity_id is required for UPDATE operations.');
        }

        // Role check: mirrors DeliveryZoneVoter::canEdit() — STORE_MANAGER | DISPATCHER | ADMINISTRATOR.
        if (!$this->rbacService->hasAnyRole($actor, [RoleName::STORE_MANAGER, RoleName::DISPATCHER, RoleName::ADMINISTRATOR])) {
            throw new AccessDeniedHttpException('Zone editing requires Store Manager, Dispatcher, or Administrator role.');
        }

        $zone = $this->deliveryZoneService->findById($entityId);

        if ($zone === null) {
            throw new \InvalidArgumentException(sprintf('Delivery zone "%s" not found.', $entityId));
        }

        // Scope check: mirrors DeliveryZoneVoter::canEdit() — canAccessDeliveryZone().
        if (!$this->scopeResolver->canAccessDeliveryZone($actor, $zone)) {
            throw new AccessDeniedHttpException(sprintf('Actor does not have scope access to zone "%s".', $entityId));
        }

        $this->deliveryZoneService->update($zone, $payload, $actor);
    }
}
