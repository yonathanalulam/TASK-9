<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Dto\Response\PaginatedEnvelope;
use App\Entity\ComplianceReport;
use App\Entity\User;
use App\Service\Export\TamperDetectionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use App\Security\Permission;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/compliance-reports')]
class ComplianceReportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TamperDetectionService $tamperDetectionService,
    ) {
    }

    #[Route('', name: 'api_compliance_reports_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::COMPLIANCE_REPORT_GENERATE);

        $body = json_decode($request->getContent(), true);

        if (!\is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        $reportType = $body['report_type'] ?? null;
        $parameters = $body['parameters'] ?? [];

        if (!\is_string($reportType) || $reportType === '') {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'report_type is required.'),
                422,
            );
        }

        if (!\is_array($parameters)) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'parameters must be an object.'),
                422,
            );
        }

        /** @var User $actor */
        $actor = $this->getUser();

        try {
            $report = $this->generateReport($reportType, $parameters, $actor);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                422,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeReport($report)), 201);
    }

    #[Route('', name: 'api_compliance_reports_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::COMPLIANCE_VIEW);

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));

        $qb = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(ComplianceReport::class, 'r')
            ->orderBy('r.generatedAt', 'DESC');

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();

        /** @var ComplianceReport[] $reports */
        $reports = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $data = array_map(
            fn (ComplianceReport $report) => $this->serializeReport($report),
            $reports,
        );

        return new JsonResponse(PaginatedEnvelope::wrap($data, $page, $perPage, $total));
    }

    #[Route('/{id}', name: 'api_compliance_reports_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::COMPLIANCE_VIEW);

        $report = $this->entityManager->getRepository(ComplianceReport::class)->find($id);

        if ($report === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Compliance report not found.'),
                404,
            );
        }

        // Include hash verification info
        $serialized = $this->serializeReport($report);
        $serialized['hash_verification'] = $this->buildHashVerification($report);

        return new JsonResponse(ApiEnvelope::wrap($serialized));
    }

    #[Route('/{id}/download', name: 'api_compliance_reports_download', methods: ['GET'])]
    public function download(string $id): BinaryFileResponse|JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::COMPLIANCE_VIEW);

        $report = $this->entityManager->getRepository(ComplianceReport::class)->find($id);

        if ($report === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Compliance report not found.'),
                404,
            );
        }

        $filePath = $report->getFilePath();

        if ($filePath === null || !file_exists($filePath)) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Report file is no longer available.'),
                404,
            );
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('compliance_%s_%s.json', $report->getReportType(), $report->getId()->toRfc4122()),
        );
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    private function generateReport(string $reportType, array $parameters, User $actor): ComplianceReport
    {
        $validTypes = ['RETENTION_SUMMARY', 'CONSENT_AUDIT', 'DATA_CLASSIFICATION', 'EXPORT_LOG', 'ACCESS_AUDIT'];

        if (!\in_array($reportType, $validTypes, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid report_type "%s". Allowed: %s',
                $reportType,
                implode(', ', $validTypes),
            ));
        }

        // Find the most recent report of this type for chain linking
        $previousReport = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(ComplianceReport::class, 'r')
            ->where('r.reportType = :reportType')
            ->setParameter('reportType', $reportType)
            ->orderBy('r.generatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // Generate the report content and write to a temp file
        $reportContent = json_encode([
            'report_type' => $reportType,
            'parameters' => $parameters,
            'generated_by' => $actor->getUsername(),
            'generated_at' => (new \DateTimeImmutable())->format('c'),
            'data' => $this->collectReportData($reportType, $parameters),
        ], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);

        $filePath = sys_get_temp_dir() . '/compliance_report_' . bin2hex(random_bytes(8)) . '.json';
        file_put_contents($filePath, $reportContent);

        $fileHash = $this->tamperDetectionService->computeFileHash($filePath);
        $chainHash = $this->tamperDetectionService->computeReportChainHash(
            $fileHash,
            $previousReport?->getTamperHashSha256(),
        );

        $report = new ComplianceReport();
        $report->setReportType($reportType);
        $report->setGeneratedBy($actor);
        $report->setParameters($parameters);
        $report->setFilePath($filePath);
        $report->setTamperHashSha256($chainHash);

        if ($previousReport !== null) {
            $report->setPreviousReportId($previousReport->getId());
            $report->setPreviousReportHash($previousReport->getTamperHashSha256());
        }

        $this->entityManager->persist($report);
        $this->entityManager->flush();

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectReportData(string $reportType, array $parameters): array
    {
        return match ($reportType) {
            'RETENTION_SUMMARY' => $this->entityManager->createQueryBuilder()
                ->select('rc.status, COUNT(rc.id) as count')
                ->from(\App\Entity\RetentionCase::class, 'rc')
                ->groupBy('rc.status')
                ->getQuery()
                ->getArrayResult(),
            'CONSENT_AUDIT' => $this->entityManager->createQueryBuilder()
                ->select('cr.consentType, cr.granted, COUNT(cr.id) as count')
                ->from(\App\Entity\ConsentRecord::class, 'cr')
                ->groupBy('cr.consentType, cr.granted')
                ->getQuery()
                ->getArrayResult(),
            'DATA_CLASSIFICATION' => $this->entityManager->createQueryBuilder()
                ->select('dc.classification, COUNT(dc.id) as count')
                ->from(\App\Entity\DataClassification::class, 'dc')
                ->groupBy('dc.classification')
                ->getQuery()
                ->getArrayResult(),
            'EXPORT_LOG' => $this->entityManager->createQueryBuilder()
                ->select('ej.status, COUNT(ej.id) as count')
                ->from(\App\Entity\ExportJob::class, 'ej')
                ->groupBy('ej.status')
                ->getQuery()
                ->getArrayResult(),
            'ACCESS_AUDIT' => $this->entityManager->createQueryBuilder()
                ->select('ae.action, COUNT(ae.id) as count')
                ->from(\App\Entity\AuditEvent::class, 'ae')
                ->groupBy('ae.action')
                ->getQuery()
                ->getArrayResult(),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHashVerification(ComplianceReport $report): array
    {
        $verification = [
            'tamper_hash_sha256' => $report->getTamperHashSha256(),
            'previous_report_id' => $report->getPreviousReportId()?->toRfc4122(),
            'previous_report_hash' => $report->getPreviousReportHash(),
            'chain_intact' => true,
        ];

        // Verify file still exists and hash matches
        $filePath = $report->getFilePath();

        if (file_exists($filePath)) {
            $currentFileHash = $this->tamperDetectionService->computeFileHash($filePath);
            $expectedChainHash = $this->tamperDetectionService->computeReportChainHash(
                $currentFileHash,
                $report->getPreviousReportHash(),
            );

            $verification['file_exists'] = true;
            $verification['chain_intact'] = $expectedChainHash === $report->getTamperHashSha256();
        } else {
            $verification['file_exists'] = false;
            $verification['chain_intact'] = false;
        }

        return $verification;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReport(ComplianceReport $report): array
    {
        return [
            'id' => $report->getId()->toRfc4122(),
            'report_type' => $report->getReportType(),
            'generated_by' => $report->getGeneratedBy()->getId()->toRfc4122(),
            'parameters' => $report->getParameters(),
            'download_url' => '/api/v1/compliance-reports/' . $report->getId()->toRfc4122() . '/download',
            'tamper_hash_sha256' => $report->getTamperHashSha256(),
            'previous_report_id' => $report->getPreviousReportId()?->toRfc4122(),
            'previous_report_hash' => $report->getPreviousReportHash(),
            'generated_at' => $report->getGeneratedAt()->format('c'),
        ];
    }
}
