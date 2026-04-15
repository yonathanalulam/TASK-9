<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Dto\Response\PaginatedEnvelope;
use App\Entity\MdmRegion;
use App\Entity\MdmRegionVersion;
use App\Entity\User;
use App\Security\Permission;
use App\Service\Authorization\ScopeResolver;
use App\Service\Region\RegionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/regions')]
class RegionController extends AbstractController
{
    public function __construct(
        private readonly RegionService $regionService,
        private readonly ScopeResolver $scopeResolver,
    ) {
    }

    #[Route('', name: 'api_regions_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::REGION_CREATE);

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
            $region = $this->regionService->create($body, $actor);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                422,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeRegion($region)), 201);
    }

    #[Route('', name: 'api_regions_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::REGION_VIEW);

        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));
        $activeOnly = $request->query->get('active_only');
        $parentId = $request->query->get('parent_id');

        $activeOnlyBool = null;
        if ($activeOnly !== null) {
            $activeOnlyBool = filter_var($activeOnly, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        // Scope filtering: restrict to accessible regions.
        $accessibleRegionIds = $this->scopeResolver->getAccessibleRegionIds($user);
        $accessibleRegionIdStrings = null;
        if ($accessibleRegionIds !== null) {
            $accessibleRegionIdStrings = array_map(
                static fn ($uuid) => $uuid->toRfc4122(),
                $accessibleRegionIds,
            );
        }

        $result = $this->regionService->list($page, $perPage, $activeOnlyBool, $parentId, $accessibleRegionIdStrings);

        $data = array_map(
            fn (MdmRegion $r) => $this->serializeRegion($r),
            $result['items'],
        );

        return new JsonResponse(PaginatedEnvelope::wrap($data, $page, $perPage, $result['total']));
    }

    #[Route('/{id}', name: 'api_regions_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $region = $this->regionService->findById($id);

        if ($region === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Region not found.'),
                404,
            );
        }

        $this->denyAccessUnlessGranted(Permission::REGION_VIEW, $region);

        // Build hierarchy path by walking up parents.
        $hierarchyPath = [];
        $current = $region;
        while ($current !== null) {
            array_unshift($hierarchyPath, [
                'id' => $current->getId()->toRfc4122(),
                'code' => $current->getCode(),
                'name' => $current->getName(),
                'hierarchy_level' => $current->getHierarchyLevel(),
            ]);
            $current = $current->getParent();
        }

        $data = $this->serializeRegion($region);
        $data['hierarchy_path'] = $hierarchyPath;

        return new JsonResponse(ApiEnvelope::wrap($data));
    }

    #[Route('/{id}', name: 'api_regions_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $region = $this->regionService->findById($id);

        if ($region === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Region not found.'),
                404,
            );
        }

        $this->denyAccessUnlessGranted(Permission::REGION_EDIT, $region);

        // Optimistic concurrency via If-Match header.
        $ifMatch = $request->headers->get('If-Match');

        if ($ifMatch === null) {
            return new JsonResponse(
                ErrorEnvelope::create('MISSING_IF_MATCH', 'If-Match header is required for updates.'),
                428,
            );
        }

        $expectedVersion = (int) trim($ifMatch, '"');

        if ($expectedVersion !== $region->getVersion()) {
            return new JsonResponse(
                ErrorEnvelope::create('CONFLICT', 'The resource has been modified by another request.', [
                    'current_version' => $region->getVersion(),
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
            $region = $this->regionService->update($region, $body, $actor);
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

        return new JsonResponse(ApiEnvelope::wrap($this->serializeRegion($region)));
    }

    #[Route('/{id}/close', name: 'api_regions_close', methods: ['POST'])]
    public function close(string $id, Request $request): JsonResponse
    {
        $region = $this->regionService->findById($id);

        if ($region === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Region not found.'),
                404,
            );
        }

        $this->denyAccessUnlessGranted(Permission::REGION_CLOSE, $region);

        $body = json_decode($request->getContent(), true);

        if (!is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        $childReassignments = $body['child_reassignments'] ?? [];
        if (!is_array($childReassignments)) {
            $childReassignments = [];
        }

        /** @var User $actor */
        $actor = $this->getUser();

        try {
            $this->regionService->close($region, $childReassignments, $actor);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                422,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap([
            'message' => 'Region closed successfully.',
        ]));
    }

    #[Route('/{id}/versions', name: 'api_regions_versions', methods: ['GET'])]
    public function versions(string $id): JsonResponse
    {
        $region = $this->regionService->findById($id);

        if ($region === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Region not found.'),
                404,
            );
        }

        $versions = $this->regionService->getVersionHistory($region);

        $data = array_map(static fn (MdmRegionVersion $v) => [
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
    private function serializeRegion(MdmRegion $region): array
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
            'created_at' => $region->getCreatedAt()->format('c'),
            'updated_at' => $region->getUpdatedAt()->format('c'),
            'version' => $region->getVersion(),
        ];
    }
}
