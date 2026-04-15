<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Dto\Response\PaginatedEnvelope;
use App\Entity\BoundaryImport;
use App\Entity\User;
use App\Security\Permission;
use App\Service\Boundary\BoundaryApplyService;
use App\Service\Boundary\BoundaryUploadService;
use App\Service\Boundary\BoundaryValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/boundaries')]
class BoundaryImportController extends AbstractController
{
    public function __construct(
        private readonly BoundaryUploadService $uploadService,
        private readonly BoundaryValidationService $validationService,
        private readonly BoundaryApplyService $applyService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/upload', name: 'api_boundaries_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::STORE_EDIT);

        $file = $request->files->get('file');

        if ($file === null) {
            return new JsonResponse(
                ErrorEnvelope::create('MISSING_FILE', 'A file must be uploaded in the "file" field.'),
                400,
            );
        }

        /** @var User $actor */
        $actor = $this->getUser();

        try {
            $import = $this->uploadService->upload($file, $actor);
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                400,
            );
        } catch (ConflictHttpException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('DUPLICATE_FILE', $e->getMessage()),
                409,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeImport($import)), 201);
    }

    #[Route('', name: 'api_boundaries_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::STORE_EDIT);

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));
        $status = $request->query->get('status');

        $result = $this->entityManager->getRepository(BoundaryImport::class)->findPaginated($page, $perPage, $status);

        $data = array_map(
            fn (BoundaryImport $import) => $this->serializeImport($import),
            $result['items'],
        );

        return new JsonResponse(PaginatedEnvelope::wrap($data, $page, $perPage, $result['total']));
    }

    #[Route('/{id}', name: 'api_boundaries_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::STORE_EDIT);

        $import = $this->entityManager->getRepository(BoundaryImport::class)->find($id);

        if ($import === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Boundary import not found.'),
                404,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeImport($import)));
    }

    #[Route('/{id}/validate', name: 'api_boundaries_validate', methods: ['POST'])]
    public function validateImport(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::STORE_EDIT);

        $import = $this->entityManager->getRepository(BoundaryImport::class)->find($id);

        if ($import === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Boundary import not found.'),
                404,
            );
        }

        if ($import->getStatus() !== BoundaryImport::STATUS_UPLOADED) {
            return new JsonResponse(
                ErrorEnvelope::create(
                    'INVALID_STATE',
                    sprintf('Cannot validate import with status "%s". Expected "UPLOADED".', $import->getStatus()),
                ),
                422,
            );
        }

        $import = $this->validationService->validate($import);

        return new JsonResponse(ApiEnvelope::wrap($this->serializeImport($import)));
    }

    #[Route('/{id}/apply', name: 'api_boundaries_apply', methods: ['POST'])]
    public function apply(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::STORE_EDIT);

        $import = $this->entityManager->getRepository(BoundaryImport::class)->find($id);

        if ($import === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Boundary import not found.'),
                404,
            );
        }

        /** @var User $actor */
        $actor = $this->getUser();

        try {
            $import = $this->applyService->apply($import, $actor);
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_STATE', $e->getMessage()),
                422,
            );
        }

        $statusCode = $import->getStatus() === BoundaryImport::STATUS_APPLIED ? 200 : 422;

        return new JsonResponse(ApiEnvelope::wrap($this->serializeImport($import)), $statusCode);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeImport(BoundaryImport $import): array
    {
        return [
            'id' => $import->getId()->toRfc4122(),
            'file_name' => $import->getFileName(),
            'file_type' => $import->getFileType(),
            'file_size' => $import->getFileSize(),
            'file_hash' => $import->getFileHash(),
            'status' => $import->getStatus(),
            'failure_reason' => $import->getFailureReason(),
            'validation_errors' => $import->getValidationErrors(),
            'uploaded_by' => $import->getUploadedBy()->getId()->toRfc4122(),
            'created_at' => $import->getCreatedAt()->format('c'),
            'updated_at' => $import->getUpdatedAt()->format('c'),
            'applied_at' => $import->getAppliedAt()?->format('c'),
        ];
    }
}
