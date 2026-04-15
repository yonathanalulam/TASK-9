<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Content;

use App\Entity\ContentItem;
use App\Entity\ContentVersion;
use App\Entity\User;
use App\Enum\ContentStatus;
use App\Service\Audit\AuditService;
use App\Service\Content\ContentRollbackService;
use App\Service\Content\ContentVersionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(ContentRollbackService::class)]
final class ContentRollbackWindowTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private ContentVersionService&MockObject $versionService;
    private AuditService&MockObject $auditService;
    private ContentRollbackService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->versionService = $this->createMock(ContentVersionService::class);
        $this->auditService = $this->createMock(AuditService::class);

        $this->service = new ContentRollbackService(
            $this->em,
            $this->versionService,
            $this->auditService,
        );
    }

    public function testRollbackAllowedForVersionCreated29DaysAgo(): void
    {
        $item = $this->createContentItem(ContentStatus::PUBLISHED);
        $version = $this->createVersion($item, 29);
        $actor = $this->createMock(User::class);

        $this->versionService->method('getVersion')->willReturn($version);
        $this->versionService->method('createVersion')->willReturn($version);
        $this->em->method('persist')->willReturnCallback(function () {});
        $this->em->method('remove')->willReturnCallback(function () {});

        // Should not throw
        $result = $this->service->rollback(
            $item,
            $version->getId()->toRfc4122(),
            'Reverting to previous version for correction',
            $actor,
        );

        self::assertSame($item, $result);
    }

    public function testRollbackAllowedForVersionCreatedExactly30DaysAgo(): void
    {
        $item = $this->createContentItem(ContentStatus::PUBLISHED);
        $version = $this->createVersion($item, 30);
        $actor = $this->createMock(User::class);

        $this->versionService->method('getVersion')->willReturn($version);
        $this->versionService->method('createVersion')->willReturn($version);
        $this->em->method('persist')->willReturnCallback(function () {});
        $this->em->method('remove')->willReturnCallback(function () {});

        // Boundary: exactly 30 days should still be allowed
        $result = $this->service->rollback(
            $item,
            $version->getId()->toRfc4122(),
            'Reverting to previous version for correction',
            $actor,
        );

        self::assertSame($item, $result);
    }

    public function testRollbackDeniedForVersionCreated31DaysAgo(): void
    {
        $item = $this->createContentItem(ContentStatus::PUBLISHED);
        $version = $this->createVersion($item, 31);
        $actor = $this->createMock(User::class);

        $this->versionService->method('getVersion')->willReturn($version);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rollback window expired.');

        $this->service->rollback(
            $item,
            $version->getId()->toRfc4122(),
            'Reverting to previous version for correction',
            $actor,
        );
    }

    public function testRollbackReasonMustBeAtLeast10Characters(): void
    {
        $item = $this->createContentItem(ContentStatus::PUBLISHED);
        $version = $this->createVersion($item, 1);
        $actor = $this->createMock(User::class);

        $this->versionService->method('getVersion')->willReturn($version);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rollback reason must be at least 10 characters.');

        $this->service->rollback(
            $item,
            $version->getId()->toRfc4122(),
            'too short',  // 9 characters
            $actor,
        );
    }

    public function testRollbackReasonOfExactly10CharactersIsAccepted(): void
    {
        $item = $this->createContentItem(ContentStatus::PUBLISHED);
        $version = $this->createVersion($item, 1);
        $actor = $this->createMock(User::class);

        $this->versionService->method('getVersion')->willReturn($version);
        $this->versionService->method('createVersion')->willReturn($version);
        $this->em->method('persist')->willReturnCallback(function () {});
        $this->em->method('remove')->willReturnCallback(function () {});

        // Exactly 10 characters: "1234567890"
        $result = $this->service->rollback(
            $item,
            $version->getId()->toRfc4122(),
            '1234567890',
            $actor,
        );

        self::assertSame($item, $result);
    }

    public function testArchivedContentCannotBeRolledBack(): void
    {
        $item = $this->createContentItem(ContentStatus::ARCHIVED);
        $actor = $this->createMock(User::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot rollback archived content.');

        $this->service->rollback(
            $item,
            Uuid::v7()->toRfc4122(),
            'Reverting to previous version for correction',
            $actor,
        );
    }

    public function testRollbackDeniedWhenTargetVersionNotFound(): void
    {
        $item = $this->createContentItem(ContentStatus::PUBLISHED);
        $actor = $this->createMock(User::class);

        $this->versionService->method('getVersion')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Target version not found.');

        $this->service->rollback(
            $item,
            Uuid::v7()->toRfc4122(),
            'Reverting to previous version for correction',
            $actor,
        );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createContentItem(ContentStatus $status): ContentItem
    {
        $item = new ContentItem();
        $item->setTitle('Test Content');
        $item->setBody('Test body content for the content item.');
        $item->setAuthorName('Test Author');
        $item->setContentType('JOB_POST');
        $item->setStatusEnum($status);

        return $item;
    }

    private function createVersion(ContentItem $item, int $daysAgo): ContentVersion
    {
        $version = new ContentVersion();
        $version->setContentItem($item);
        $version->setVersionNumber(1);
        $version->setTitle($item->getTitle());
        $version->setBody($item->getBody());
        $version->setTags([]);
        $version->setContentType($item->getContentType());
        $version->setStatusAtCreation($item->getStatus());

        // Use reflection to set createdAt to the desired date in the past
        $reflClass = new \ReflectionClass($version);
        $reflProp = $reflClass->getProperty('createdAt');
        $reflProp->setValue($version, new \DateTimeImmutable(sprintf('-%d days', $daysAgo)));

        // Also set the createdBy mock
        $user = $this->createMock(User::class);
        $version->setCreatedBy($user);

        return $version;
    }
}
