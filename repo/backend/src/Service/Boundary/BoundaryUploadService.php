<?php

declare(strict_types=1);

namespace App\Service\Boundary;

use App\Entity\BoundaryImport;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Repository\BoundaryImportRepository;
use App\Service\Audit\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BoundaryUploadService
{
    private const MAX_FILE_SIZE = 25 * 1024 * 1024; // 25 MB

    private const ALLOWED_EXTENSIONS = ['geojson', 'json', 'zip'];

    private const ALLOWED_MIME_TYPES = [
        'application/json',
        'application/geo+json',
        'application/zip',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BoundaryImportRepository $boundaryImportRepository,
        private readonly AuditService $auditService,
        private readonly string $projectDir,
    ) {
    }

    public function upload(UploadedFile $file, User $actor): BoundaryImport
    {
        $this->validateFileType($file);
        $this->validateFileSize($file);

        $content = file_get_contents($file->getPathname());

        if ($content === false) {
            throw new BadRequestHttpException('Unable to read uploaded file.');
        }

        $hash = hash('sha256', $content);

        $existing = $this->boundaryImportRepository->findByHash($hash);

        if ($existing !== null) {
            throw new ConflictHttpException(sprintf(
                'A boundary file with identical content has already been uploaded (import ID: %s).',
                $existing->getId()->toRfc4122(),
            ));
        }

        $fileType = $this->resolveFileType($file);
        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize() !== false ? (int) $file->getSize() : strlen($content);
        $import = new BoundaryImport();
        $storageFileName = $import->getId()->toRfc4122() . '_' . $originalName;
        $storagePath = 'var/uploads/boundaries/' . $storageFileName;
        $absolutePath = $this->projectDir . '/' . $storagePath;

        $uploadDir = dirname($absolutePath);

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0o755, true);
        }

        $file->move($uploadDir, $storageFileName);

        $import->setFileName($originalName);
        $import->setFileType($fileType);
        $import->setFileSize($fileSize);
        $import->setFileHash($hash);
        $import->setStoragePath($storagePath);
        $import->setStatus(BoundaryImport::STATUS_UPLOADED);
        $import->setUploadedBy($actor);

        $this->entityManager->persist($import);
        $this->entityManager->flush();

        $this->auditService->record(
            action: AuditAction::BOUNDARY_UPLOADED->value,
            entityType: 'BoundaryImport',
            entityId: $import->getId()->toBinary(),
            oldValues: null,
            newValues: [
                'file_name' => $originalName,
                'file_type' => $fileType,
                'file_hash' => $hash,
            ],
            actor: $actor,
        );

        return $import;
    }

    private function validateFileType(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new BadRequestHttpException(sprintf(
                'Invalid file extension "%s". Allowed: %s.',
                $extension,
                implode(', ', self::ALLOWED_EXTENSIONS),
            ));
        }

        $mimeType = $file->getMimeType();

        if ($mimeType !== null && !in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new BadRequestHttpException(sprintf(
                'Invalid MIME type "%s". Allowed: %s.',
                $mimeType,
                implode(', ', self::ALLOWED_MIME_TYPES),
            ));
        }
    }

    private function validateFileSize(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new BadRequestHttpException(sprintf(
                'File size exceeds the maximum allowed size of %d MB.',
                self::MAX_FILE_SIZE / (1024 * 1024),
            ));
        }
    }

    private function resolveFileType(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'zip') {
            return BoundaryImport::FILE_TYPE_SHAPEFILE_ZIP;
        }

        return BoundaryImport::FILE_TYPE_GEOJSON;
    }
}
