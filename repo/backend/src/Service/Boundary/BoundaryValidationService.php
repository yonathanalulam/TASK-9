<?php

declare(strict_types=1);

namespace App\Service\Boundary;

use App\Entity\BoundaryImport;
use App\Enum\AuditAction;
use App\Repository\BoundaryImportRepository;
use App\Service\Audit\AuditService;
use Doctrine\ORM\EntityManagerInterface;

class BoundaryValidationService
{
    private const VALID_GEOMETRY_TYPES = [
        'Point',
        'Polygon',
        'MultiPolygon',
        'LineString',
        'MultiLineString',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BoundaryImportRepository $boundaryImportRepository,
        private readonly AuditService $auditService,
        private readonly string $projectDir,
    ) {
    }

    public function validate(BoundaryImport $import): BoundaryImport
    {
        $import->setStatus(BoundaryImport::STATUS_VALIDATING);
        $this->entityManager->flush();

        $errors = [];

        try {
            if ($import->getFileType() === BoundaryImport::FILE_TYPE_GEOJSON) {
                $errors = $this->validateGeoJson($import);
            } elseif ($import->getFileType() === BoundaryImport::FILE_TYPE_SHAPEFILE_ZIP) {
                $errors = $this->validateShapefile($import);
            } else {
                $errors[] = sprintf('Unsupported file type: %s.', $import->getFileType());
            }
        } catch (\Throwable $e) {
            $errors[] = sprintf('Unexpected validation error: %s', $e->getMessage());
        }

        if (count($errors) > 0) {
            $import->setStatus(BoundaryImport::STATUS_FAILED);
            $import->setFailureReason('Validation failed with ' . count($errors) . ' error(s).');
            $import->setValidationErrors($errors);
            $this->entityManager->flush();

            $this->auditService->record(
                action: AuditAction::BOUNDARY_VALIDATED->value,
                entityType: 'BoundaryImport',
                entityId: $import->getId()->toBinary(),
                oldValues: null,
                newValues: [
                    'status' => BoundaryImport::STATUS_FAILED,
                    'error_count' => count($errors),
                ],
            );

            return $import;
        }

        $import->setStatus(BoundaryImport::STATUS_VALIDATED);
        $import->setValidationErrors(null);
        $import->setFailureReason(null);
        $this->entityManager->flush();

        $this->auditService->record(
            action: AuditAction::BOUNDARY_VALIDATED->value,
            entityType: 'BoundaryImport',
            entityId: $import->getId()->toBinary(),
            oldValues: null,
            newValues: [
                'status' => BoundaryImport::STATUS_VALIDATED,
            ],
        );

        return $import;
    }

    /**
     * @return string[]
     */
    private function validateGeoJson(BoundaryImport $import): array
    {
        $errors = [];
        $filePath = $this->projectDir . '/' . $import->getStoragePath();

        $content = file_get_contents($filePath);

        if ($content === false) {
            return ['Unable to read GeoJSON file from storage.'];
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            return ['File does not contain valid JSON.'];
        }

        if (!isset($data['type']) || $data['type'] !== 'FeatureCollection') {
            $errors[] = 'Root object must have type "FeatureCollection".';
        }

        if (!isset($data['features']) || !is_array($data['features'])) {
            $errors[] = 'Root object must contain a "features" array.';

            return $errors;
        }

        foreach ($data['features'] as $index => $feature) {
            $featureErrors = $this->validateFeature($feature, $index);
            $errors = array_merge($errors, $featureErrors);
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private function validateShapefile(BoundaryImport $import): array
    {
        $errors = [];
        $filePath = $this->projectDir . '/' . $import->getStoragePath();

        $zip = new \ZipArchive();
        $result = $zip->open($filePath);

        if ($result !== true) {
            return ['Unable to open ZIP archive. Error code: ' . $result];
        }

        $requiredExtensions = ['shp', 'dbf', 'prj'];
        $foundExtensions = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name === false) {
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (in_array($ext, $requiredExtensions, true)) {
                $foundExtensions[$ext] = true;
            }
        }

        foreach ($requiredExtensions as $ext) {
            if (!isset($foundExtensions[$ext])) {
                $errors[] = sprintf('Required shapefile component .%s is missing from the archive.', $ext);
            }
        }

        $zip->close();

        return $errors;
    }

    /**
     * @return string[]
     */
    private function validateFeature(mixed $feature, int $index): array
    {
        $errors = [];
        $prefix = sprintf('Feature[%d]', $index);

        if (!is_array($feature)) {
            return [sprintf('%s: Feature must be an object.', $prefix)];
        }

        // Validate geometry
        if (!isset($feature['geometry']) || !is_array($feature['geometry'])) {
            $errors[] = sprintf('%s: Missing or invalid "geometry" object.', $prefix);
        } else {
            $geometryType = $feature['geometry']['type'] ?? null;

            if (!is_string($geometryType) || !in_array($geometryType, self::VALID_GEOMETRY_TYPES, true)) {
                $errors[] = sprintf(
                    '%s: Invalid geometry type "%s". Allowed: %s.',
                    $prefix,
                    is_string($geometryType) ? $geometryType : 'null',
                    implode(', ', self::VALID_GEOMETRY_TYPES),
                );
            }
        }

        // Validate properties
        if (!isset($feature['properties']) || !is_array($feature['properties'])) {
            $errors[] = sprintf('%s: Missing or invalid "properties" object.', $prefix);
        } else {
            $hasId = isset($feature['properties']['id']) && $feature['properties']['id'] !== '';
            $hasName = isset($feature['properties']['name']) && $feature['properties']['name'] !== '';

            if (!$hasId && !$hasName) {
                $errors[] = sprintf('%s: Properties must contain at least an "id" or "name" field.', $prefix);
            }
        }

        return $errors;
    }
}
