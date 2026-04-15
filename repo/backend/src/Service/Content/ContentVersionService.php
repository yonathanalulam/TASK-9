<?php

declare(strict_types=1);

namespace App\Service\Content;

use App\Entity\ContentItem;
use App\Entity\ContentVersion;
use App\Entity\User;
use App\Repository\ContentVersionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class ContentVersionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContentVersionRepository $contentVersionRepository,
    ) {
    }

    /**
     * Returns all versions for a content item ordered by versionNumber DESC.
     *
     * @return list<ContentVersion>
     */
    public function getTimeline(ContentItem $item): array
    {
        return $this->contentVersionRepository->findBy(
            ['contentItem' => $item],
            ['versionNumber' => 'DESC'],
        );
    }

    public function getVersion(string $versionId): ?ContentVersion
    {
        return $this->contentVersionRepository->find($versionId);
    }

    /**
     * Field-by-field comparison between two versions.
     *
     * @return list<array{field: string, before: mixed, after: mixed}>
     */
    public function diff(ContentVersion $v1, ContentVersion $v2): array
    {
        $diffs = [];

        $fields = [
            'title' => [
                fn (ContentVersion $v) => $v->getTitle(),
            ],
            'body' => [
                fn (ContentVersion $v) => $v->getBody(),
            ],
            'tags' => [
                fn (ContentVersion $v) => $v->getTags(),
            ],
            'contentType' => [
                fn (ContentVersion $v) => $v->getContentType(),
            ],
        ];

        foreach ($fields as $fieldName => [$getter]) {
            $before = $getter($v1);
            $after = $getter($v2);

            if ($before !== $after) {
                $diffs[] = [
                    'field' => $fieldName,
                    'before' => $before,
                    'after' => $after,
                ];
            }
        }

        return $diffs;
    }

    /**
     * Creates a new version snapshot of the current state of a ContentItem.
     */
    public function createVersion(
        ContentItem $item,
        User $actor,
        ?string $changeReason = null,
        bool $isRollback = false,
        ?string $rolledBackToVersionId = null,
    ): ContentVersion {
        // Determine next version number.
        $latestVersion = $this->contentVersionRepository->findOneBy(
            ['contentItem' => $item],
            ['versionNumber' => 'DESC'],
        );
        $nextVersionNumber = $latestVersion !== null ? $latestVersion->getVersionNumber() + 1 : 1;

        $version = new ContentVersion();
        $version->setContentItem($item);
        $version->setVersionNumber($nextVersionNumber);
        $version->setTitle($item->getTitle());
        $version->setBody($item->getBody());
        $version->setTags($item->getTagValues());
        $version->setContentType($item->getContentType());
        $version->setStatusAtCreation($item->getStatus());
        $version->setChangeReason($changeReason);
        $version->setIsRollback($isRollback);
        $version->setCreatedBy($actor);

        if ($rolledBackToVersionId !== null) {
            $version->setRolledBackToVersionId(Uuid::fromString($rolledBackToVersionId));
        }

        $this->entityManager->persist($version);

        return $version;
    }
}
