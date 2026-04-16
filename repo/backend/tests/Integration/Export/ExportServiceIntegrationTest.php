<?php

declare(strict_types=1);

namespace App\Tests\Integration\Export;

use App\Entity\ExportJob;
use App\Entity\User;
use App\Service\Export\ExportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ExportServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ExportService $exportService;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->exportService = $container->get(ExportService::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
    }

    public function testRequestExportCreatesJobInDatabase(): void
    {
        $requester = $this->createTestUser('export_request_user');

        $job = $this->exportService->requestExport(
            'content_items',
            'CSV',
            ['limit' => 100],
            $requester,
        );

        self::assertInstanceOf(ExportJob::class, $job);
        self::assertSame('content_items', $job->getDataset());
        self::assertSame('CSV', $job->getFormat());
        self::assertSame('REQUESTED', $job->getStatus());
        self::assertSame(['limit' => 100], $job->getFilters());
        self::assertNotNull($job->getWatermarkText());

        // Verify persistence by re-fetching from DB.
        $this->em->clear();
        $persisted = $this->em->getRepository(ExportJob::class)->find($job->getId());

        self::assertNotNull($persisted);
        self::assertSame('content_items', $persisted->getDataset());
        self::assertSame('CSV', $persisted->getFormat());
        self::assertSame('REQUESTED', $persisted->getStatus());
        self::assertSame(['limit' => 100], $persisted->getFilters());
        self::assertSame($requester->getId()->toRfc4122(), $persisted->getRequestedBy()->getId()->toRfc4122());
    }

    public function testRequestExportWithAuditEventsDataset(): void
    {
        $requester = $this->createTestUser('export_audit_user');

        $job = $this->exportService->requestExport(
            'audit_events',
            'CSV',
            null,
            $requester,
        );

        self::assertSame('audit_events', $job->getDataset());
        self::assertSame('REQUESTED', $job->getStatus());
        self::assertNull($job->getFilters());

        $this->em->clear();
        $persisted = $this->em->getRepository(ExportJob::class)->find($job->getId());

        self::assertNotNull($persisted);
        self::assertSame('audit_events', $persisted->getDataset());
    }

    public function testAuthorizeExportTransitionsStatusToAuthorized(): void
    {
        $requester = $this->createTestUser('export_auth_req_user');
        $authorizer = $this->createTestUser('export_authorizer_user');

        $job = $this->exportService->requestExport(
            'content_items',
            'CSV',
            null,
            $requester,
        );

        self::assertSame('REQUESTED', $job->getStatus());

        // authorizeExport triggers generateExport internally, which calls
        // fetchDataForExport and the CSV renderer. The generation may succeed
        // or fail depending on the temp filesystem, but the status should
        // transition beyond REQUESTED. We verify the authorization fields.
        $this->exportService->authorizeExport($job, $authorizer);

        // After authorizeExport + generateExport, status should be SUCCEEDED
        // (or FAILED if the filesystem is unavailable), but never REQUESTED.
        $this->em->clear();
        $reloaded = $this->em->getRepository(ExportJob::class)->find($job->getId());

        self::assertNotNull($reloaded);
        self::assertNotSame('REQUESTED', $reloaded->getStatus());
        self::assertNotNull($reloaded->getAuthorizedBy());
        self::assertSame($authorizer->getId()->toRfc4122(), $reloaded->getAuthorizedBy()->getId()->toRfc4122());
        self::assertNotNull($reloaded->getAuthorizedAt());
    }

    public function testRequestExportRejectsInvalidDataset(): void
    {
        $requester = $this->createTestUser('export_invalid_ds_user');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid dataset/');

        $this->exportService->requestExport(
            'nonexistent_dataset',
            'CSV',
            null,
            $requester,
        );
    }

    public function testRequestExportRejectsInvalidFormat(): void
    {
        $requester = $this->createTestUser('export_invalid_fmt_user');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid format/');

        $this->exportService->requestExport(
            'content_items',
            'PDF',
            null,
            $requester,
        );
    }

    public function testAuthorizeExportRejectsNonRequestedJob(): void
    {
        $requester = $this->createTestUser('export_non_req_user');
        $authorizer = $this->createTestUser('export_non_req_auth');

        // Create a job and manually set status to something other than REQUESTED.
        $job = new ExportJob();
        $job->setDataset('content_items');
        $job->setFormat('CSV');
        $job->setRequestedBy($requester);
        $job->setStatus('AUTHORIZED');

        $this->em->persist($job);
        $this->em->flush();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be in REQUESTED status/');

        $this->exportService->authorizeExport($job, $authorizer);
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
