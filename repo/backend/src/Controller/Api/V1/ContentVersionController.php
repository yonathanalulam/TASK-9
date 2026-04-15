<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Entity\ContentItem;
use App\Entity\ContentVersion;
use App\Entity\User;
use App\Service\Content\ContentRollbackService;
use App\Service\Content\ContentService;
use App\Service\Content\ContentVersionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Security\Permission;
use Symfony\Component\Routing\Attribute\Route;

class ContentVersionController extends AbstractController
{
    public function __construct(
        private readonly ContentService $contentService,
        private readonly ContentVersionService $contentVersionService,
        private readonly ContentRollbackService $contentRollbackService,
    ) {
    }

    #[Route('/api/v1/content/{contentId}/versions', name: 'api_content_versions_timeline', methods: ['GET'])]
    public function timeline(string $contentId): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::CONTENT_VIEW);

        $item = $this->contentService->findById($contentId);

        if ($item === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Content item not found.'),
                404,
            );
        }

        $versions = $this->contentVersionService->getTimeline($item);

        $data = array_map(
            fn (ContentVersion $v) => $this->serializeVersion($v),
            $versions,
        );

        return new JsonResponse(ApiEnvelope::wrap($data));
    }

    #[Route('/api/v1/content/{contentId}/versions/{versionId}', name: 'api_content_versions_show', methods: ['GET'])]
    public function show(string $contentId, string $versionId): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::CONTENT_VIEW);

        $item = $this->contentService->findById($contentId);

        if ($item === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Content item not found.'),
                404,
            );
        }

        $version = $this->contentVersionService->getVersion($versionId);

        if ($version === null || $version->getContentItem()->getId()->toRfc4122() !== $item->getId()->toRfc4122()) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Version not found.'),
                404,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeVersion($version)));
    }

    #[Route('/api/v1/content/{contentId}/versions/{v1Id}/diff/{v2Id}', name: 'api_content_versions_diff', methods: ['GET'])]
    public function diff(string $contentId, string $v1Id, string $v2Id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::CONTENT_VIEW);

        $item = $this->contentService->findById($contentId);

        if ($item === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Content item not found.'),
                404,
            );
        }

        $v1 = $this->contentVersionService->getVersion($v1Id);
        $v2 = $this->contentVersionService->getVersion($v2Id);

        if ($v1 === null || $v1->getContentItem()->getId()->toRfc4122() !== $item->getId()->toRfc4122()) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Version v1 not found.'),
                404,
            );
        }

        if ($v2 === null || $v2->getContentItem()->getId()->toRfc4122() !== $item->getId()->toRfc4122()) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Version v2 not found.'),
                404,
            );
        }

        $diffs = $this->contentVersionService->diff($v1, $v2);

        return new JsonResponse(ApiEnvelope::wrap([
            'v1' => [
                'id' => $v1->getId()->toRfc4122(),
                'version_number' => $v1->getVersionNumber(),
            ],
            'v2' => [
                'id' => $v2->getId()->toRfc4122(),
                'version_number' => $v2->getVersionNumber(),
            ],
            'changes' => $diffs,
        ]));
    }

    #[Route('/api/v1/content/{contentId}/rollback', name: 'api_content_rollback', methods: ['POST'])]
    public function rollback(string $contentId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::CONTENT_ROLLBACK);

        $item = $this->contentService->findById($contentId);

        if ($item === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Content item not found.'),
                404,
            );
        }

        $body = json_decode($request->getContent(), true);

        if (!is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        $targetVersionId = $body['target_version_id'] ?? '';
        $reason = $body['reason'] ?? '';

        if (!is_string($targetVersionId) || $targetVersionId === '') {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'target_version_id is required.'),
                422,
            );
        }

        if (!is_string($reason) || $reason === '') {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'reason is required.'),
                422,
            );
        }

        /** @var User $actor */
        $actor = $this->getUser();

        try {
            $item = $this->contentRollbackService->rollback($item, $targetVersionId, $reason, $actor);
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
    private function serializeVersion(ContentVersion $version): array
    {
        return [
            'id' => $version->getId()->toRfc4122(),
            'content_item_id' => $version->getContentItem()->getId()->toRfc4122(),
            'version_number' => $version->getVersionNumber(),
            'title' => $version->getTitle(),
            'body' => $version->getBody(),
            'tags' => $version->getTags(),
            'content_type' => $version->getContentType(),
            'status_at_creation' => $version->getStatusAtCreation(),
            'change_reason' => $version->getChangeReason(),
            'is_rollback' => $version->isRollback(),
            'rolled_back_to_version_id' => $version->getRolledBackToVersionId()?->toRfc4122(),
            'created_by' => $version->getCreatedBy()->getId()->toRfc4122(),
            'created_at' => $version->getCreatedAt()->format('c'),
        ];
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
