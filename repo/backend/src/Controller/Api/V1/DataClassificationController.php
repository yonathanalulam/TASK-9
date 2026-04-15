<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Dto\Response\PaginatedEnvelope;
use App\Entity\DataClassification;
use App\Entity\User;
use App\Service\Governance\ClassifiedFieldService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Security\Permission;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/classifications')]
class DataClassificationController extends AbstractController
{
    private const array VALID_CLASSIFICATIONS = [
        'PUBLIC',
        'INTERNAL',
        'CONFIDENTIAL',
        'RESTRICTED',
        'PII',
        'SENSITIVE',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClassifiedFieldService $classifiedFieldService,
    ) {
    }

    #[Route('', name: 'api_classifications_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::CLASSIFICATION_MANAGE);

        $body = json_decode($request->getContent(), true);

        if (!\is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        /** @var User $actor */
        $actor = $this->getUser();

        try {
            $classification = $this->createClassification($body, $actor);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                422,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeClassification($classification)), 201);
    }

    #[Route('', name: 'api_classifications_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::CLASSIFICATION_VIEW);

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));
        $entityType = $request->query->get('entity_type');
        $classification = $request->query->get('classification');

        $qb = $this->entityManager->createQueryBuilder()
            ->select('dc')
            ->from(DataClassification::class, 'dc')
            ->orderBy('dc.classifiedAt', 'DESC');

        if ($entityType !== null && $entityType !== '') {
            $qb->andWhere('dc.entityType = :entityType')
                ->setParameter('entityType', $entityType);
        }

        if ($classification !== null && $classification !== '') {
            $qb->andWhere('dc.classification = :classification')
                ->setParameter('classification', $classification);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(dc.id)')->getQuery()->getSingleScalarResult();

        /** @var DataClassification[] $classifications */
        $classifications = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $data = array_map(
            fn (DataClassification $dc) => $this->serializeClassification($dc),
            $classifications,
        );

        return new JsonResponse(PaginatedEnvelope::wrap($data, $page, $perPage, $total));
    }

    #[Route('/{id}', name: 'api_classifications_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::CLASSIFICATION_MANAGE);

        $existing = $this->entityManager->getRepository(DataClassification::class)->find($id);

        if ($existing === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Data classification not found.'),
                404,
            );
        }

        $body = json_decode($request->getContent(), true);

        if (!\is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        $classificationValue = $body['classification'] ?? null;

        if (!\is_string($classificationValue) || $classificationValue === '') {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'classification is required.'),
                422,
            );
        }

        if (!\in_array($classificationValue, self::VALID_CLASSIFICATIONS, true)) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', sprintf(
                    'Invalid classification "%s". Allowed: %s',
                    $classificationValue,
                    implode(', ', self::VALID_CLASSIFICATIONS),
                )),
                422,
            );
        }

        /** @var User $actor */
        $actor = $this->getUser();

        $existing->setClassification($classificationValue);
        $existing->setClassifiedBy($actor);

        $this->entityManager->flush();

        return new JsonResponse(ApiEnvelope::wrap($this->serializeClassification($existing)));
    }

    private function createClassification(array $body, User $actor): DataClassification
    {
        $entityType = $body['entity_type'] ?? null;
        $entityId = $body['entity_id'] ?? null;
        $fieldName = $body['field_name'] ?? null;
        $classificationValue = $body['classification'] ?? null;

        if (!\is_string($entityType) || $entityType === '') {
            throw new \InvalidArgumentException('entity_type is required.');
        }

        if (!\is_string($entityId) || $entityId === '') {
            throw new \InvalidArgumentException('entity_id is required.');
        }

        if (!\is_string($classificationValue) || $classificationValue === '') {
            throw new \InvalidArgumentException('classification is required.');
        }

        if (!\in_array($classificationValue, self::VALID_CLASSIFICATIONS, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid classification "%s". Allowed: %s',
                $classificationValue,
                implode(', ', self::VALID_CLASSIFICATIONS),
            ));
        }

        $dc = new DataClassification();
        $dc->setEntityType($entityType);
        $dc->setEntityId($entityId);
        $dc->setFieldName($fieldName);
        $dc->setClassification($classificationValue);
        $dc->setClassifiedBy($actor);

        $this->entityManager->persist($dc);
        $this->entityManager->flush();

        return $dc;
    }

    #[Route('/encrypted-fields/store', name: 'api_classifications_encrypted_store', methods: ['POST'])]
    public function storeEncryptedField(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::CLASSIFICATION_MANAGE);

        $body = json_decode($request->getContent(), true);

        if (!\is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        $entityType = $body['entity_type'] ?? null;
        $entityId = $body['entity_id'] ?? null;
        $fieldName = $body['field_name'] ?? null;
        $value = $body['value'] ?? null;

        if (!\is_string($entityType) || $entityType === '') {
            return new JsonResponse(ErrorEnvelope::create('VALIDATION_ERROR', 'entity_type is required.'), 422);
        }
        if (!\is_string($entityId) || $entityId === '') {
            return new JsonResponse(ErrorEnvelope::create('VALIDATION_ERROR', 'entity_id is required.'), 422);
        }
        if (!\is_string($fieldName) || $fieldName === '') {
            return new JsonResponse(ErrorEnvelope::create('VALIDATION_ERROR', 'field_name is required.'), 422);
        }
        if (!\is_string($value)) {
            return new JsonResponse(ErrorEnvelope::create('VALIDATION_ERROR', 'value is required.'), 422);
        }

        /** @var User $actor */
        $actor = $this->getUser();

        $encrypted = $this->classifiedFieldService->storeFieldValue($entityType, $entityId, $fieldName, $value, $actor);

        if (!$encrypted) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_CLASSIFIED', 'Field does not require encryption per its classification.'),
                422,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap(['stored' => true]), 201);
    }

    #[Route('/encrypted-fields/retrieve', name: 'api_classifications_encrypted_retrieve', methods: ['POST'])]
    public function retrieveEncryptedField(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::CLASSIFICATION_VIEW);

        $body = json_decode($request->getContent(), true);

        if (!\is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        $entityType = $body['entity_type'] ?? null;
        $entityId = $body['entity_id'] ?? null;
        $fieldName = $body['field_name'] ?? null;

        if (!\is_string($entityType) || $entityType === '') {
            return new JsonResponse(ErrorEnvelope::create('VALIDATION_ERROR', 'entity_type is required.'), 422);
        }
        if (!\is_string($entityId) || $entityId === '') {
            return new JsonResponse(ErrorEnvelope::create('VALIDATION_ERROR', 'entity_id is required.'), 422);
        }
        if (!\is_string($fieldName) || $fieldName === '') {
            return new JsonResponse(ErrorEnvelope::create('VALIDATION_ERROR', 'field_name is required.'), 422);
        }

        /** @var User $actor */
        $actor = $this->getUser();

        $value = $this->classifiedFieldService->retrieveFieldValue($entityType, $entityId, $fieldName, $actor);

        if ($value === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'No encrypted value found for this field.'),
                404,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap(['value' => $value]));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeClassification(DataClassification $dc): array
    {
        return [
            'id' => $dc->getId()->toRfc4122(),
            'entity_type' => $dc->getEntityType(),
            'entity_id' => bin2hex($dc->getEntityId()),
            'field_name' => $dc->getFieldName(),
            'classification' => $dc->getClassification(),
            'classified_by' => $dc->getClassifiedBy()->getId()->toRfc4122(),
            'classified_at' => $dc->getClassifiedAt()->format('c'),
        ];
    }
}
