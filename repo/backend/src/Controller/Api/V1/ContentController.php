<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Dto\Response\PaginatedEnvelope;
use App\Entity\ContentItem;
use App\Entity\User;
use App\Service\Authorization\ScopeResolver;
use App\Service\Content\ContentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Security\Permission;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/content')]
class ContentController extends AbstractController
{
    public function __construct(
        private readonly ContentService $contentService,
        private readonly ScopeResolver $scopeResolver,
    ) {
    }

    #[Route('', name: 'api_content_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::CONTENT_CREATE);

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
            $item = $this->contentService->create($body, $actor);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                422,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeContentItem($item)), 201);
    }

    #[Route('', name: 'api_content_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::CONTENT_VIEW);

        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));
        $contentType = $request->query->get('content_type');
        $storeId = $request->query->get('store_id');
        $regionId = $request->query->get('region_id');
        $status = $request->query->get('status');

        // Scope filtering: restrict content to user's authorized stores and regions.
        $accessibleStoreIds = $this->scopeResolver->getAccessibleStoreIds($user);
        $accessibleStoreIdStrings = null;
        if ($accessibleStoreIds !== null) {
            $accessibleStoreIdStrings = array_map(
                static fn ($uuid) => $uuid->toRfc4122(),
                $accessibleStoreIds,
            );
        }

        $accessibleRegionIds = $this->scopeResolver->getAccessibleRegionIds($user);
        $accessibleRegionIdStrings = null;
        if ($accessibleRegionIds !== null) {
            $accessibleRegionIdStrings = array_map(
                static fn ($uuid) => $uuid->toRfc4122(),
                $accessibleRegionIds,
            );
        }

        $result = $this->contentService->list($page, $perPage, $contentType, $storeId, $regionId, $status, $accessibleStoreIdStrings, $accessibleRegionIdStrings);

        $data = array_map(
            fn (ContentItem $item) => $this->serializeContentItem($item),
            $result['items'],
        );

        return new JsonResponse(PaginatedEnvelope::wrap($data, $page, $perPage, $result['total']));
    }

    #[Route('/{id}', name: 'api_content_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::CONTENT_VIEW);

        /** @var User $user */
        $user = $this->getUser();

        $item = $this->contentService->findById($id);

        if ($item === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Content item not found.'),
                404,
            );
        }

        // Scope check: if user has limited scope, verify item is accessible.
        $accessibleStoreIds = $this->scopeResolver->getAccessibleStoreIds($user);

        // Check store-scoped content.
        if ($accessibleStoreIds !== null && $item->getStoreId() !== null) {
            $itemStoreRfc = $item->getStoreId()->toRfc4122();
            $accessible = false;
            foreach ($accessibleStoreIds as $uuid) {
                if ($uuid->toRfc4122() === $itemStoreRfc) {
                    $accessible = true;
                    break;
                }
            }
            if (!$accessible) {
                return new JsonResponse(
                    ErrorEnvelope::create('FORBIDDEN', 'You do not have access to this content item.'),
                    403,
                );
            }
        }

        // Check region-scoped content (no store_id, but has region_id).
        if ($accessibleStoreIds !== null && $item->getStoreId() === null && $item->getRegionId() !== null) {
            $accessibleRegionIds = $this->scopeResolver->getAccessibleRegionIds($user);
            if ($accessibleRegionIds !== null) {
                $itemRegionRfc = $item->getRegionId()->toRfc4122();
                $regionAccessible = false;
                foreach ($accessibleRegionIds as $uuid) {
                    if ($uuid->toRfc4122() === $itemRegionRfc) {
                        $regionAccessible = true;
                        break;
                    }
                }
                if (!$regionAccessible) {
                    return new JsonResponse(
                        ErrorEnvelope::create('FORBIDDEN', 'You do not have access to this content item.'),
                        403,
                    );
                }
            }
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeContentItem($item)));
    }

    #[Route('/{id}', name: 'api_content_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::CONTENT_EDIT);

        $item = $this->contentService->findById($id);

        if ($item === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Content item not found.'),
                404,
            );
        }

        // Optimistic concurrency via If-Match header.
        $ifMatch = $request->headers->get('If-Match');

        if ($ifMatch === null) {
            return new JsonResponse(
                ErrorEnvelope::create('MISSING_IF_MATCH', 'If-Match header is required for updates.'),
                428,
            );
        }

        $expectedVersion = (int) trim($ifMatch, '"');

        if ($expectedVersion !== $item->getVersion()) {
            return new JsonResponse(
                ErrorEnvelope::create('CONFLICT', 'The resource has been modified by another request.', [
                    'current_version' => $item->getVersion(),
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
            $item = $this->contentService->update($item, $body, $actor);
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

        return new JsonResponse(ApiEnvelope::wrap($this->serializeContentItem($item)));
    }

    #[Route('/{id}/publish', name: 'api_content_publish', methods: ['POST'])]
    public function publish(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::CONTENT_PUBLISH);

        $item = $this->contentService->findById($id);

        if ($item === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Content item not found.'),
                404,
            );
        }

        /** @var User $actor */
        $actor = $this->getUser();

        try {
            $item = $this->contentService->publish($item, $actor);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                422,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeContentItem($item)));
    }

    #[Route('/{id}/archive', name: 'api_content_archive', methods: ['POST'])]
    public function archive(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::CONTENT_ARCHIVE);

        $item = $this->contentService->findById($id);

        if ($item === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Content item not found.'),
                404,
            );
        }

        /** @var User $actor */
        $actor = $this->getUser();

        try {
            $item = $this->contentService->archive($item, $actor);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                422,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeContentItem($item)));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeContentItem(ContentItem $item): array
    {
        return [
            'id' => $item->getId()->toRfc4122(),
            'content_type' => $item->getContentType(),
            'title' => $item->getTitle(),
            'body' => $item->getBody(),
            'author_name' => $item->getAuthorName(),
            'source_type' => $item->getSourceType(),
            'source_reference' => $item->getSourceReference(),
            'published_at' => $item->getPublishedAt()?->format('c'),
            'store_id' => $item->getStoreId()?->toRfc4122(),
            'region_id' => $item->getRegionId()?->toRfc4122(),
            'status' => $item->getStatus(),
            'view_count' => $item->getViewCount(),
            'reply_count' => $item->getReplyCount(),
            'version' => $item->getVersion(),
            'tags' => $item->getTagValues(),
            'created_at' => $item->getCreatedAt()->format('c'),
            'updated_at' => $item->getUpdatedAt()->format('c'),
        ];
    }
}
