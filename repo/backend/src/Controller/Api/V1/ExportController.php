<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Dto\Response\PaginatedEnvelope;
use App\Entity\ExportJob;
use App\Entity\User;
use App\Service\Export\ExportService;
use App\Service\Export\TamperDetectionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use App\Security\Permission;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/exports')]
class ExportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExportService $exportService,
        private readonly TamperDetectionService $tamperDetectionService,
    ) {
    }

    #[Route('', name: 'api_exports_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EXPORT_REQUEST);

        $body = json_decode($request->getContent(), true);

        if (!\is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        /** @var User $actor */
        $actor = $this->getUser();

        $dataset = $body['dataset'] ?? null;
        $format = $body['format'] ?? null;
        $filters = $body['filters'] ?? null;

        if (!\is_string($dataset) || $dataset === '') {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'dataset is required.'),
                422,
            );
        }

        if (!\is_string($format) || $format === '') {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'format is required.'),
                422,
            );
        }

        try {
            $job = $this->exportService->requestExport($dataset, $format, $filters, $actor);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                422,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeExportJob($job)), 201);
    }

    #[Route('/{id}/authorize', name: 'api_exports_authorize', methods: ['POST'])]
    public function authorize(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EXPORT_AUTHORIZE);

        $job = $this->entityManager->getRepository(ExportJob::class)->find($id);

        if ($job === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Export job not found.'),
                404,
            );
        }

        /** @var User $actor */
        $actor = $this->getUser();

        try {
            $this->exportService->authorizeExport($job, $actor);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                422,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeExportJob($job)));
    }

    #[Route('/{id}', name: 'api_exports_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EXPORT_VIEW);

        $job = $this->entityManager->getRepository(ExportJob::class)->find($id);

        if ($job === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Export job not found.'),
                404,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeExportJob($job)));
    }

    #[Route('/{id}/download', name: 'api_exports_download', methods: ['GET'])]
    public function download(string $id): JsonResponse|BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(Permission::EXPORT_DOWNLOAD);

        $job = $this->entityManager->getRepository(ExportJob::class)->find($id);

        if ($job === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Export job not found.'),
                404,
            );
        }

        // Ownership check: only the requester, authorizer, or admin can download.
        /** @var User $user */
        $user = $this->getUser();
        $isOwner = $job->getRequestedBy()->getId()->equals($user->getId());
        $isAuthorizer = $job->getAuthorizedBy()?->getId()->equals($user->getId()) ?? false;
        $isAdmin = $this->isGranted(Permission::EXPORT_AUTHORIZE);

        if (!$isOwner && !$isAuthorizer && !$isAdmin) {
            return new JsonResponse(
                ErrorEnvelope::create('FORBIDDEN', 'You do not have permission to download this export.'),
                403,
            );
        }

        if ($job->getStatus() !== 'SUCCEEDED') {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', sprintf(
                    'Export must be in SUCCEEDED status to download. Current: %s',
                    $job->getStatus(),
                )),
                422,
            );
        }

        $filePath = $job->getFilePath();

        if ($filePath === null || !file_exists($filePath)) {
            return new JsonResponse(
                ErrorEnvelope::create('FILE_NOT_FOUND', 'Export file is not available.'),
                404,
            );
        }

        // Verify tamper detection hash
        $currentHash = $this->tamperDetectionService->computeFileHash($filePath);

        if ($currentHash !== $job->getTamperHashSha256()) {
            return new JsonResponse(
                ErrorEnvelope::create('TAMPER_DETECTED', 'Export file integrity check failed.'),
                422,
            );
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $job->getFileName() ?? 'export.' . strtolower($job->getFormat()),
        );

        return $response;
    }

    #[Route('', name: 'api_exports_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::EXPORT_VIEW);

        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));

        $qb = $this->entityManager->createQueryBuilder()
            ->select('j')
            ->from(ExportJob::class, 'j')
            ->orderBy('j.requestedAt', 'DESC');

        // Non-admin users only see exports they requested or authorized.
        if (!$this->isGranted(Permission::EXPORT_AUTHORIZE)) {
            $qb->andWhere('j.requestedBy = :userId')
                ->setParameter('userId', $user->getId(), 'uuid');
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(j.id)')->getQuery()->getSingleScalarResult();

        /** @var ExportJob[] $jobs */
        $jobs = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $data = array_map(
            fn (ExportJob $job) => $this->serializeExportJob($job),
            $jobs,
        );

        return new JsonResponse(PaginatedEnvelope::wrap($data, $page, $perPage, $total));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeExportJob(ExportJob $job): array
    {
        return [
            'id' => $job->getId()->toRfc4122(),
            'dataset' => $job->getDataset(),
            'format' => $job->getFormat(),
            'status' => $job->getStatus(),
            'requested_by' => $job->getRequestedBy()->getId()->toRfc4122(),
            'authorized_by' => $job->getAuthorizedBy()?->getId()->toRfc4122(),
            'filters' => $job->getFilters(),
            'file_name' => $job->getFileName(),
            'watermark_text' => $job->getWatermarkText(),
            'tamper_hash_sha256' => $job->getTamperHashSha256(),
            'requested_at' => $job->getRequestedAt()->format('c'),
            'authorized_at' => $job->getAuthorizedAt()?->format('c'),
            'completed_at' => $job->getCompletedAt()?->format('c'),
            'expires_at' => $job->getExpiresAt()?->format('c'),
        ];
    }
}
