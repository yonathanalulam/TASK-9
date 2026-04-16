<?php

declare(strict_types=1);

namespace App\Tests\Integration\Content;

use App\Entity\ContentItem;
use App\Entity\User;
use App\Enum\ContentStatus;
use App\Enum\ContentType;
use App\Service\Content\ContentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ContentServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ContentService $contentService;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->contentService = $container->get(ContentService::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
    }

    public function testCreateContentItemPersistsToDatabase(): void
    {
        $actor = $this->createTestUser('content_create_user');

        $item = $this->contentService->create([
            'title' => 'Integration Test Article',
            'body' => 'This is the body of the integration test content item.',
            'author_name' => 'Test Author',
            'content_type' => ContentType::JOB_POST->value,
            'tags' => ['php', 'testing'],
        ], $actor);

        self::assertInstanceOf(ContentItem::class, $item);
        self::assertSame('Integration Test Article', $item->getTitle());
        self::assertSame(ContentStatus::DRAFT, $item->getStatusEnum());

        // Verify persistence by re-fetching from DB.
        $this->em->clear();
        $persisted = $this->em->getRepository(ContentItem::class)->find($item->getId());

        self::assertNotNull($persisted);
        self::assertSame('Integration Test Article', $persisted->getTitle());
        self::assertSame('This is the body of the integration test content item.', $persisted->getBody());
        self::assertSame('Test Author', $persisted->getAuthorName());
        self::assertSame(ContentType::JOB_POST->value, $persisted->getContentType());
        self::assertSame(ContentStatus::DRAFT->value, $persisted->getStatus());
    }

    public function testPublishContentChangesStatusToPublished(): void
    {
        $actor = $this->createTestUser('content_publish_user');

        $item = $this->contentService->create([
            'title' => 'Publishable Article',
            'body' => 'Body content for publish test.',
            'author_name' => 'Publish Author',
            'content_type' => ContentType::OPERATIONAL_NOTICE->value,
        ], $actor);

        self::assertSame(ContentStatus::DRAFT, $item->getStatusEnum());

        $published = $this->contentService->publish($item, $actor);

        self::assertSame(ContentStatus::PUBLISHED, $published->getStatusEnum());
        self::assertNotNull($published->getPublishedAt());

        // Verify in DB after clear.
        $this->em->clear();
        $reloaded = $this->em->getRepository(ContentItem::class)->find($item->getId());

        self::assertNotNull($reloaded);
        self::assertSame(ContentStatus::PUBLISHED->value, $reloaded->getStatus());
        self::assertNotNull($reloaded->getPublishedAt());
    }

    public function testArchiveContentChangesStatusToArchived(): void
    {
        $actor = $this->createTestUser('content_archive_user');

        $item = $this->contentService->create([
            'title' => 'Archivable Article',
            'body' => 'Body content for archive test.',
            'author_name' => 'Archive Author',
            'content_type' => ContentType::VENDOR_BULLETIN->value,
        ], $actor);

        $archived = $this->contentService->archive($item, $actor);

        self::assertSame(ContentStatus::ARCHIVED, $archived->getStatusEnum());

        // Verify in DB after clear.
        $this->em->clear();
        $reloaded = $this->em->getRepository(ContentItem::class)->find($item->getId());

        self::assertNotNull($reloaded);
        self::assertSame(ContentStatus::ARCHIVED->value, $reloaded->getStatus());
    }

    public function testListContentWithStatusFilter(): void
    {
        $actor = $this->createTestUser('content_list_user');

        // Create two DRAFT items and one PUBLISHED item.
        $draft1 = $this->contentService->create([
            'title' => 'Draft One',
            'body' => 'Draft one body.',
            'author_name' => 'List Author',
            'content_type' => ContentType::JOB_POST->value,
        ], $actor);

        $draft2 = $this->contentService->create([
            'title' => 'Draft Two',
            'body' => 'Draft two body.',
            'author_name' => 'List Author',
            'content_type' => ContentType::JOB_POST->value,
        ], $actor);

        $toPublish = $this->contentService->create([
            'title' => 'Published Item',
            'body' => 'Published item body.',
            'author_name' => 'List Author',
            'content_type' => ContentType::JOB_POST->value,
        ], $actor);
        $this->contentService->publish($toPublish, $actor);

        // List only PUBLISHED items.
        $result = $this->contentService->list(
            page: 1,
            perPage: 50,
            status: ContentStatus::PUBLISHED->value,
        );

        self::assertArrayHasKey('items', $result);
        self::assertArrayHasKey('total', $result);
        self::assertGreaterThanOrEqual(1, $result['total']);

        // Every returned item must be PUBLISHED.
        foreach ($result['items'] as $contentItem) {
            self::assertSame(ContentStatus::PUBLISHED->value, $contentItem->getStatus());
        }
    }

    public function testListContentWithContentTypeFilter(): void
    {
        $actor = $this->createTestUser('content_type_filter_user');

        $this->contentService->create([
            'title' => 'Job Post Item',
            'body' => 'Job post body.',
            'author_name' => 'Filter Author',
            'content_type' => ContentType::JOB_POST->value,
        ], $actor);

        $this->contentService->create([
            'title' => 'Vendor Bulletin Item',
            'body' => 'Vendor bulletin body.',
            'author_name' => 'Filter Author',
            'content_type' => ContentType::VENDOR_BULLETIN->value,
        ], $actor);

        $result = $this->contentService->list(
            page: 1,
            perPage: 50,
            contentType: ContentType::VENDOR_BULLETIN->value,
        );

        self::assertGreaterThanOrEqual(1, $result['total']);

        foreach ($result['items'] as $contentItem) {
            self::assertSame(ContentType::VENDOR_BULLETIN->value, $contentItem->getContentType());
        }
    }

    private function createTestUser(string $username): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setDisplayName('Test ' . $username);
        $user->setStatus('ACTIVE');

        $hashedPassword = $this->passwordHasher->hashPassword($user, 'V@lid1Password!');
        $user->setPasswordHash($hashedPassword);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
