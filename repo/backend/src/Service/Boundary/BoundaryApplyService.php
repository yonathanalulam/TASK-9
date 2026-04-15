<?php

declare(strict_types=1);

namespace App\Service\Boundary;

use App\Entity\AdministrativeArea;
use App\Entity\BoundaryFile;
use App\Entity\BoundaryImport;
use App\Entity\CommunityGrid;
use App\Entity\MdmRegion;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Repository\BoundaryImportRepository;
use App\Repository\MdmRegionRepository;
use App\Service\Audit\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class BoundaryApplyService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BoundaryImportRepository $boundaryImportRepository,
        private readonly MdmRegionRepository $mdmRegionRepository,
        private readonly AuditService $auditService,
        private readonly string $projectDir,
    ) {
    }

    public function apply(BoundaryImport $import, User $actor): BoundaryImport
    {
        if ($import->getStatus() !== BoundaryImport::STATUS_VALIDATED) {
            throw new BadRequestHttpException(sprintf(
                'Cannot apply import with status "%s". Import must be in VALIDATED status.',
                $import->getStatus(),
            ));
        }

        $this->entityManager->beginTransaction();

        try {
            $features = $this->extractFeatures($import);

            foreach ($features as $feature) {
                $this->processFeature($feature, $import, $actor);
            }

            $import->setStatus(BoundaryImport::STATUS_APPLIED);
            $import->setAppliedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $e) {
            $this->entityManager->rollback();

            $import->setStatus(BoundaryImport::STATUS_FAILED);
            $import->setFailureReason('Apply failed: ' . $e->getMessage());

            // Re-fetch the entity manager state after rollback
            if ($this->entityManager->isOpen()) {
                $this->entityManager->flush();
            }

            $this->auditService->record(
                action: AuditAction::BOUNDARY_APPLIED->value,
                entityType: 'BoundaryImport',
                entityId: $import->getId()->toBinary(),
                oldValues: null,
                newValues: [
                    'status' => BoundaryImport::STATUS_FAILED,
                    'failure_reason' => $e->getMessage(),
                ],
                actor: $actor,
            );

            return $import;
        }

        $this->auditService->record(
            action: AuditAction::BOUNDARY_APPLIED->value,
            entityType: 'BoundaryImport',
            entityId: $import->getId()->toBinary(),
            oldValues: null,
            newValues: [
                'status' => BoundaryImport::STATUS_APPLIED,
                'feature_count' => count($features),
            ],
            actor: $actor,
        );

        return $import;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractFeatures(BoundaryImport $import): array
    {
        $filePath = $this->projectDir . '/' . $import->getStoragePath();

        if ($import->getFileType() === BoundaryImport::FILE_TYPE_GEOJSON) {
            return $this->extractGeoJsonFeatures($filePath);
        }

        if ($import->getFileType() === BoundaryImport::FILE_TYPE_SHAPEFILE_ZIP) {
            return $this->extractShapefileFeatures($filePath);
        }

        throw new \RuntimeException(sprintf('Unsupported file type: %s', $import->getFileType()));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractGeoJsonFeatures(string $filePath): array
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new \RuntimeException('Unable to read GeoJSON file.');
        }

        $data = json_decode($content, true);

        if (!is_array($data) || !isset($data['features']) || !is_array($data['features'])) {
            throw new \RuntimeException('Invalid GeoJSON structure.');
        }

        return $data['features'];
    }

    /**
     * Extracts features from shapefile ZIP by reading the .dbf metadata.
     * Returns minimal feature representations for entity creation.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractShapefileFeatures(string $filePath): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('Unable to open shapefile ZIP archive.');
        }

        $features = [];

        // Look for .dbf file for attribute data
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name === false) {
                continue;
            }

            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'dbf') {
                $baseName = pathinfo($name, PATHINFO_FILENAME);

                // Create a minimal feature per shapefile record set
                $features[] = [
                    'properties' => [
                        'name' => $baseName,
                        'type' => 'administrative_area',
                        'source' => 'shapefile',
                    ],
                    'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [],
                    ],
                ];
            }
        }

        $zip->close();

        return $features;
    }

    /**
     * @param array<string, mixed> $feature
     */
    private function processFeature(array $feature, BoundaryImport $import, User $actor): void
    {
        $properties = $feature['properties'] ?? [];
        $featureType = strtolower((string) ($properties['type'] ?? 'administrative_area'));
        $regionCode = $properties['region_code'] ?? null;

        $region = $this->resolveRegion($regionCode);

        $featureName = (string) ($properties['name'] ?? $properties['id'] ?? 'unnamed');
        $featureCode = (string) ($properties['id'] ?? $properties['code'] ?? $this->generateCode($featureName));

        if ($featureType === 'community_grid') {
            $entity = $this->createCommunityGrid($featureCode, $featureName, $region);
        } else {
            $entity = $this->createAdministrativeArea(
                $featureCode,
                $featureName,
                $properties['area_type'] ?? 'district',
                $region,
            );
        }

        // Create BoundaryFile record linking geometry to the entity
        $boundaryFile = new BoundaryFile();
        $boundaryFile->setEntityType($featureType === 'community_grid' ? 'CommunityGrid' : 'AdministrativeArea');
        $boundaryFile->setEntityId($entity->getId()->toBinary());
        $boundaryFile->setFileName($import->getFileName());
        $boundaryFile->setFilePath($import->getStoragePath());
        $boundaryFile->setFileHash($import->getFileHash());
        $boundaryFile->setUploadedBy($actor);

        $this->entityManager->persist($boundaryFile);
    }

    private function resolveRegion(?string $regionCode): MdmRegion
    {
        if ($regionCode !== null) {
            $region = $this->mdmRegionRepository->findOneBy(['code' => $regionCode]);

            if ($region !== null) {
                return $region;
            }
        }

        // Fall back to the first active region
        $region = $this->mdmRegionRepository->findOneBy(['isActive' => true]);

        if ($region === null) {
            throw new \RuntimeException('No active region found to assign boundary features.');
        }

        return $region;
    }

    private function createAdministrativeArea(
        string $code,
        string $name,
        string $areaType,
        MdmRegion $region,
    ): AdministrativeArea {
        $area = new AdministrativeArea();
        $area->setCode($code);
        $area->setName($name);
        $area->setAreaType($areaType);
        $area->setRegion($region);

        $this->entityManager->persist($area);

        return $area;
    }

    private function createCommunityGrid(
        string $code,
        string $name,
        MdmRegion $region,
    ): CommunityGrid {
        $grid = new CommunityGrid();
        $grid->setCode($code);
        $grid->setName($name);
        $grid->setRegion($region);

        $this->entityManager->persist($grid);

        return $grid;
    }

    private function generateCode(string $name): string
    {
        $code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name) ?? '', 0, 15));

        return $code !== '' ? $code : 'BND' . substr(bin2hex(random_bytes(4)), 0, 8);
    }
}
