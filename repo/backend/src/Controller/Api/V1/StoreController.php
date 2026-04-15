<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Dto\Response\PaginatedEnvelope;
use App\Entity\Store;
use App\Entity\StoreVersion;
use App\Entity\User;
use App\Security\Permission;
use App\Service\Authorization\ScopeResolver;
use App\Service\Store\StoreService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/stores')]
class StoreController extends AbstractController
{
    public function __construct(
        private readonly StoreService $storeService,
        private readonly ScopeResolver $scopeResolver,
    ) {
    }

    #[Route('', name: 'api_stores_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::STORE_CREATE);

        $body = json_decode($request->getContent(), true);

        if (!is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        /** @var User $actor */
        $actor = $this->getUser();

        try {
            $store = $this->storeService->create($body, $actor);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                422,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeStore($store)), 201);
    }

    #[Route('', name: 'api_stores_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::STORE_VIEW);

        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));
        $regionId = $request->query->get('region_id');
        $type = $request->query->get('type');
        $status = $request->query->get('status');

        // Scope-filter: restrict to stores the user is authorized to see.
        $accessibleStoreIds = $this->scopeResolver->getAccessibleStoreIds($user);
        $accessibleStoreIdStrings = null;
        if ($accessibleStoreIds !== null) {
            $accessibleStoreIdStrings = array_map(
                static fn ($uuid) => $uuid->toRfc4122(),
                $accessibleStoreIds,
            );
        }

        $result = $this->storeService->list($page, $perPage, $regionId, $type, $status, $accessibleStoreIdStrings);

        $data = array_map(
            fn (Store $s) => $this->serializeStore($s),
            $result['items'],
        );

        return new JsonResponse(PaginatedEnvelope::wrap($data, $page, $perPage, $result['total']));
    }

    #[Route('/{id}', name: 'api_stores_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $store = $this->storeService->findById($id);

        if ($store === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Store not found.'),
                404,
            );
        }

        $this->denyAccessUnlessGranted(Permission::STORE_VIEW, $store);

        return new JsonResponse(ApiEnvelope::wrap($this->serializeStore($store)));
    }

    #[Route('/{id}', name: 'api_stores_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $store = $this->storeService->findById($id);

        if ($store === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Store not found.'),
                404,
            );
        }

        $this->denyAccessUnlessGranted(Permission::STORE_EDIT, $store);

        // Optimistic concurrency via If-Match header.
        $ifMatch = $request->headers->get('If-Match');

        if ($ifMatch === null) {
            return new JsonResponse(
                ErrorEnvelope::create('MISSING_IF_MATCH', 'If-Match header is required for updates.'),
                428,
            );
        }

        $expectedVersion = (int) trim($ifMatch, '"');

        if ($expectedVersion !== $store->getVersion()) {
            return new JsonResponse(
                ErrorEnvelope::create('CONFLICT', 'The resource has been modified by another request.', [
                    'current_version' => $store->getVersion(),
                ]),
                409,
            );
        }

        $body = json_decode($request->getContent(), true);

        if (!is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        /** @var User $actor */
        $actor = $this->getUser();

        try {
            $store = $this->storeService->update($store, $body, $actor);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                422,
            );
        } catch (\Doctrine\ORM\OptimisticLockException) {
            return new JsonResponse(
                ErrorEnvelope::create('CONFLICT', 'The resource has been modified by another request.'),
                409,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeStore($store)));
    }

    #[Route('/{id}/versions', name: 'api_stores_versions', methods: ['GET'])]
    public function versions(string $id): JsonResponse
    {
        $store = $this->storeService->findById($id);

        if ($store === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Store not found.'),
                404,
            );
        }

        $this->denyAccessUnlessGranted(Permission::STORE_VIEW, $store);

        $versions = $this->storeService->getVersionHistory($store);

        $data = array_map(static fn (StoreVersion $v) => [
            'id' => $v->getId()->toRfc4122(),
            'version_number' => $v->getVersionNumber(),
            'change_type' => $v->getChangeType(),
            'snapshot' => $v->getSnapshot(),
            'changed_by' => $v->getChangedBy()->getId()->toRfc4122(),
            'changed_at' => $v->getChangedAt()->format('c'),
            'change_reason' => $v->getChangeReason(),
        ], $versions);

        return new JsonResponse(ApiEnvelope::wrap($data));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeStore(Store $store): array
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
            'created_at' => $store->getCreatedAt()->format('c'),
            'updated_at' => $store->getUpdatedAt()->format('c'),
            'version' => $store->getVersion(),
        ];
    }
}
