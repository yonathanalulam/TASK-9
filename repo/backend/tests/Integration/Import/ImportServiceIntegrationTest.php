<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import;

use App\Entity\ImportBatch;
use App\Entity\ImportItem;
use App\Entity\User;
use App\Service\Import\FingerprintService;
use App\Service\Import\NormalizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ImportServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private NormalizationService $normalizationService;
    private FingerprintService $fingerprintService;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->normalizationService = $container->get(NormalizationService::class);
        $this->fingerprintService = $container->get(FingerprintService::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
    }

    public function testCreateBatchWithItemsPersistsBatchAndItems(): void
    {
        $actor = $this->createTestUser('import_batch_user');

        $batch = new ImportBatch();
        $batch->setSourceName('integration-test-source');
        $batch->setFileName('test-import.csv');
        $batch->setCreatedBy($actor);
        $batch->setTotalItems(2);
        $batch->setStatus('PROCESSING');

        $this->em->persist($batch);

        $item1 = $this->createImportItem($batch, 'Senior Software Engineer', 'Acme Corp', 'New York', 'Build scalable systems');
        $item2 = $this->createImportItem($batch, 'Junior Developer', 'Beta Inc', 'London', 'Entry level development role');

        $this->em->persist($item1);
        $this->em->persist($item2);

        $batch->setProcessedItems(2);
        $batch->setStatus('COMPLETED');
        $batch->setCompletedAt(new \DateTimeImmutable());

        $this->em->flush();

        // Verify batch persistence.
        $this->em->clear();
        $persistedBatch = $this->em->getRepository(ImportBatch::class)->find($batch->getId());

        self::assertNotNull($persistedBatch);
        self::assertSame('integration-test-source', $persistedBatch->getSourceName());
        self::assertSame('test-import.csv', $persistedBatch->getFileName());
        self::assertSame('COMPLETED', $persistedBatch->getStatus());
        self::assertSame(2, $persistedBatch->getTotalItems());
        self::assertSame(2, $persistedBatch->getProcessedItems());
        self::assertNotNull($persistedBatch->getCompletedAt());

        // Verify items persistence.
        $persistedItems = $this->em->getRepository(ImportItem::class)->findBy(
            ['importBatch' => $persistedBatch->getId()],
        );

        self::assertCount(2, $persistedItems);
    }

    public function testImportItemsHaveComputedDedupFingerprints(): void
    {
        $actor = $this->createTestUser('import_fp_user');

        $batch = new ImportBatch();
        $batch->setSourceName('fingerprint-test-source');
        $batch->setCreatedBy($actor);
        $batch->setTotalItems(1);
        $batch->setStatus('PROCESSING');

        $this->em->persist($batch);

        $rawTitle = 'Senior Software Engineer';
        $rawCompany = 'Acme Corp';
        $rawLocation = 'New York';
        $rawBody = 'Build scalable distributed systems for millions of users.';

        $item = $this->createImportItem($batch, $rawTitle, $rawCompany, $rawLocation, $rawBody);
        $this->em->persist($item);
        $this->em->flush();

        // Compute expected fingerprint using the same services.
        $normalizedTitle = $this->normalizationService->normalize($rawTitle);
        $normalizedCompany = $this->normalizationService->normalize($rawCompany);
        $normalizedLocation = $this->normalizationService->normalize($rawLocation);
        $normalizedBody = $this->normalizationService->normalize($rawBody);

        $expectedFingerprint = $this->fingerprintService->computeFingerprint(
            $normalizedTitle,
            $normalizedCompany,
            $normalizedLocation,
            $normalizedBody,
        );

        // Verify from DB.
        $this->em->clear();
        $persistedItem = $this->em->getRepository(ImportItem::class)->find($item->getId());

        self::assertNotNull($persistedItem);
        self::assertSame($expectedFingerprint, $persistedItem->getDedupFingerprint());
        self::assertSame(64, strlen($persistedItem->getDedupFingerprint()), 'Fingerprint must be a SHA-256 hex string (64 chars).');
    }

    public function testImportItemNormalizationIsPersisted(): void
    {
        $actor = $this->createTestUser('import_norm_user');

        $batch = new ImportBatch();
        $batch->setSourceName('normalization-test');
        $batch->setCreatedBy($actor);
        $batch->setTotalItems(1);
        $batch->setStatus('PROCESSING');

        $this->em->persist($batch);

        $rawTitle = '  Senior   Software   ENGINEER!!! ';
        $item = $this->createImportItem($batch, $rawTitle, null, null, null);

        $this->em->persist($item);
        $this->em->flush();

        $this->em->clear();
        $persisted = $this->em->getRepository(ImportItem::class)->find($item->getId());

        self::assertNotNull($persisted);
        self::assertSame($rawTitle, $persisted->getRawTitle());
        self::assertSame('senior software engineer', $persisted->getNormalizedTitle());
    }

    public function testBatchItemCountsAreAccurate(): void
    {
        $actor = $this->createTestUser('import_count_user');

        $batch = new ImportBatch();
        $batch->setSourceName('count-test-source');
        $batch->setCreatedBy($actor);
        $batch->setTotalItems(3);
        $batch->setProcessedItems(3);
        $batch->setMergedItems(1);
        $batch->setReviewItems(1);
        $batch->setStatus('REVIEW_NEEDED');

        $this->em->persist($batch);

        for ($i = 0; $i < 3; $i++) {
            $item = $this->createImportItem($batch, "Item Title $i", null, null, null);
            $this->em->persist($item);
        }

        $this->em->flush();

        $this->em->clear();
        $reloaded = $this->em->getRepository(ImportBatch::class)->find($batch->getId());

        self::assertNotNull($reloaded);
        self::assertSame(3, $reloaded->getTotalItems());
        self::assertSame(3, $reloaded->getProcessedItems());
        self::assertSame(1, $reloaded->getMergedItems());
        self::assertSame(1, $reloaded->getReviewItems());
        self::assertSame('REVIEW_NEEDED', $reloaded->getStatus());
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

    private function createImportItem(
        ImportBatch $batch,
        string $rawTitle,
        ?string $rawCompany,
        ?string $rawLocation,
        ?string $rawBody,
    ): ImportItem {
        $normalizedTitle = $this->normalizationService->normalize($rawTitle);
        $normalizedCompany = $rawCompany !== null ? $this->normalizationService->normalize($rawCompany) : null;
        $normalizedLocation = $rawLocation !== null ? $this->normalizationService->normalize($rawLocation) : null;
        $normalizedBody = $rawBody !== null ? $this->normalizationService->normalize($rawBody) : null;

        $fingerprint = $this->fingerprintService->computeFingerprint(
            $normalizedTitle,
            $normalizedCompany,
            $normalizedLocation,
            $normalizedBody,
        );

        $item = new ImportItem();
        $item->setImportBatch($batch);
        $item->setRawTitle($rawTitle);
        $item->setRawCompany($rawCompany);
        $item->setRawLocation($rawLocation);
        $item->setRawBody($rawBody);
        $item->setNormalizedTitle($normalizedTitle);
        $item->setNormalizedCompany($normalizedCompany);
        $item->setNormalizedLocation($normalizedLocation);
        $item->setDedupFingerprint($fingerprint);

        return $item;
    }
}
