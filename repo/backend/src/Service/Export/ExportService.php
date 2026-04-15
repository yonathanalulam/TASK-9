<?php

declare(strict_types=1);

namespace App\Service\Export;

use App\Entity\ExportJob;
use App\Entity\User;
use App\Repository\ExportJobRepository;
use App\Service\Audit\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ExportService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExportJobRepository $exportJobRepository,
        private readonly CsvExportRenderer $csvExportRenderer,
        private readonly ExportFileNamer $exportFileNamer,
        private readonly TamperDetectionService $tamperDetectionService,
        private readonly AuditService $auditService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Create a new export request.
     */
    /** Canonical dataset values supported by fetchDataForExport(). */
    public const array VALID_DATASETS = ['content_items', 'audit_events'];

    /** Canonical export format values — only formats with real renderers. */
    public const array VALID_FORMATS = ['CSV'];

    public function requestExport(
        string $dataset,
        string $format,
        ?array $filters,
        User $requester,
    ): ExportJob {
        if (!\in_array($dataset, self::VALID_DATASETS, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid dataset "%s". Allowed: %s', $dataset, implode(', ', self::VALID_DATASETS)),
            );
        }

        if (!\in_array(strtoupper($format), self::VALID_FORMATS, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid format "%s". Allowed: %s', $format, implode(', ', self::VALID_FORMATS)),
            );
        }

        $job = new ExportJob();
        $job->setDataset($dataset);
        $job->setFormat(strtoupper($format));
        $job->setFilters($filters);
        $job->setRequestedBy($requester);
        $job->setWatermarkText(sprintf(
            '%s %s',
            $requester->getUsername(),
            (new \DateTimeImmutable())->format('m/d/Y h:i A'),
        ));

        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $this->logger->info('Export requested', [
            'export_id' => $job->getId()->toRfc4122(),
            'dataset' => $dataset,
            'format' => $format,
            'requester' => $requester->getUsername(),
        ]);

        $this->auditService->record(
            action: 'EXPORT_REQUESTED',
            entityType: 'ExportJob',
            entityId: $job->getId()->toRfc4122(),
            oldValues: null,
            newValues: [
                'dataset' => $dataset,
                'format' => $format,
                'filters' => $filters,
            ],
            actor: $requester,
        );

        return $job;
    }

    /**
     * Authorize an export job and trigger file generation.
     */
    public function authorizeExport(ExportJob $job, User $authorizer): void
    {
        if ($job->getStatus() !== 'REQUESTED') {
            throw new \InvalidArgumentException(
                sprintf('Export job must be in REQUESTED status to authorize. Current: %s', $job->getStatus()),
            );
        }

        $job->setStatus('AUTHORIZED');
        $job->setAuthorizedBy($authorizer);
        $job->setAuthorizedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->auditService->record(
            action: 'EXPORT_AUTHORIZED',
            entityType: 'ExportJob',
            entityId: $job->getId()->toRfc4122(),
            oldValues: ['status' => 'REQUESTED'],
            newValues: ['status' => 'AUTHORIZED'],
            actor: $authorizer,
        );

        // Trigger file generation immediately after authorization.
        $this->generateExport($job);
    }

    /**
     * Generate the export file.
     */
    public function generateExport(ExportJob $job): void
    {
        if ($job->getStatus() !== 'AUTHORIZED') {
            throw new \InvalidArgumentException(
                sprintf('Export job must be in AUTHORIZED status to generate. Current: %s', $job->getStatus()),
            );
        }

        $job->setStatus('RUNNING');
        $this->entityManager->flush();

        try {
            // Fetch data based on dataset and filters
            $data = $this->fetchDataForExport($job);

            // Generate file — CSV is the only supported format.
            // Validation in requestExport() rejects unsupported formats before
            // a job is created, so this assertion should never fire.
            if ($job->getFormat() !== 'CSV') {
                throw new \LogicException(
                    sprintf('Unsupported format reached generation: %s', $job->getFormat()),
                );
            }
            $filePath = $this->csvExportRenderer->render($job, $data);

            $extension = strtolower($job->getFormat());
            $fileName = $this->exportFileNamer->generate(
                $job->getDataset(),
                $job->getRequestedBy()->getUsername(),
                $extension,
            );

            // Compute tamper detection hash
            $hash = $this->tamperDetectionService->computeFileHash($filePath);

            $job->setFilePath($filePath);
            $job->setFileName($fileName);
            $job->setTamperHashSha256($hash);
            $job->setStatus('SUCCEEDED');
            $job->setCompletedAt(new \DateTimeImmutable());
            $job->setExpiresAt(new \DateTimeImmutable('+7 days'));

            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $job->setStatus('FAILED');
            $job->setCompletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->logger->error('Export generation failed', [
                'export_id' => $job->getId()->toRfc4122(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Fetch data for the given export job based on dataset and filters.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchDataForExport(ExportJob $job): array
    {
        $dataset = $job->getDataset();
        $filters = $job->getFilters() ?? [];

        $qb = $this->entityManager->createQueryBuilder();

        return match ($dataset) {
            'content_items' => $qb
                ->select('ci')
                ->from(\App\Entity\ContentItem::class, 'ci')
                ->orderBy('ci.createdAt', 'DESC')
                ->setMaxResults($filters['limit'] ?? 10000)
                ->getQuery()
                ->getArrayResult(),
            'audit_events' => $qb
                ->select('ae')
                ->from(\App\Entity\AuditEvent::class, 'ae')
                ->orderBy('ae.occurredAt', 'DESC')
                ->setMaxResults($filters['limit'] ?? 10000)
                ->getQuery()
                ->getArrayResult(),
            default => [],
        };
    }
}
