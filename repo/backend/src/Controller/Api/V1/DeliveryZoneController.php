<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Dto\Response\PaginatedEnvelope;
use App\Entity\DeliveryWindow;
use App\Entity\DeliveryZone;
use App\Entity\User;
use App\Entity\ZoneOrderRule;
use App\Entity\ZoneProductRule;
use App\Repository\DeliveryWindowRepository;
use App\Repository\StoreRepository;
use App\Repository\ZoneOrderRuleRepository;
use App\Repository\ZoneProductRuleRepository;
use App\Security\Permission;
use App\Service\DeliveryZone\DeliveryZoneService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
class DeliveryZoneController extends AbstractController
{
    public function __construct(
        private readonly DeliveryZoneService $zoneService,
        private readonly StoreRepository $storeRepository,
        private readonly DeliveryWindowRepository $windowRepository,
        private readonly ZoneProductRuleRepository $productRuleRepository,
        private readonly ZoneOrderRuleRepository $orderRuleRepository,
        private readonly \Doctrine\ORM\EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/stores/{storeId}/delivery-zones', name: 'api_delivery_zones_create', methods: ['POST'])]
    public function create(string $storeId, Request $request): JsonResponse
    {
        $store = $this->storeRepository->find($storeId);

        if ($store === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Store not found.'),
                404,
            );
        }

        $this->denyAccessUnlessGranted(Permission::ZONE_CREATE, $store);

        $body = json_decode($request->getContent(), true);

        if (!is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        /** @var User $actor */
        $actor = $this->getUser();

        try {
            $zone = $this->zoneService->create($body, $storeId, $actor);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                422,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeZone($zone)), 201);
    }

    #[Route('/stores/{storeId}/delivery-zones', name: 'api_delivery_zones_list', methods: ['GET'])]
    public function list(string $storeId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ZONE_VIEW);

        $store = $this->storeRepository->find($storeId);

        if ($store === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Store not found.'),
                404,
            );
        }

        // Scope check: verify user can access this store
        $this->denyAccessUnlessGranted('STORE_VIEW', $store);

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));

        $result = $this->zoneService->list($storeId, $page, $perPage);

        $data = array_map(
            fn (DeliveryZone $z) => $this->serializeZone($z),
            $result['items'],
        );

        return new JsonResponse(PaginatedEnvelope::wrap($data, $page, $perPage, $result['total']));
    }

    #[Route('/delivery-zones/{id}', name: 'api_delivery_zones_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $zone = $this->zoneService->findById($id);

        if ($zone === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Delivery zone not found.'),
                404,
            );
        }

        $this->denyAccessUnlessGranted(Permission::ZONE_VIEW, $zone);

        // Include windows and rules.
        $windows = $this->windowRepository->findBy(['zone' => $zone], ['dayOfWeek' => 'ASC', 'startTime' => 'ASC']);
        $productRules = $this->productRuleRepository->findBy(['zone' => $zone]);
        $orderRules = $this->orderRuleRepository->findBy(['zone' => $zone]);

        $data = $this->serializeZone($zone);
        $data['windows'] = array_map(static fn (DeliveryWindow $w) => [
            'id' => $w->getId()->toRfc4122(),
            'day_of_week' => $w->getDayOfWeek(),
            'start_time' => $w->getStartTime()->format('H:i'),
            'end_time' => $w->getEndTime()->format('H:i'),
            'is_active' => $w->isActive(),
        ], $windows);
        $data['product_rules'] = array_map(static fn (ZoneProductRule $r) => [
            'id' => $r->getId()->toRfc4122(),
            'rule_type' => $r->getRuleType(),
            'rule_config' => $r->getRuleConfig(),
            'is_active' => $r->isActive(),
        ], $productRules);
        $data['order_rules'] = array_map(static fn (ZoneOrderRule $r) => [
            'id' => $r->getId()->toRfc4122(),
            'rule_type' => $r->getRuleType(),
            'rule_config' => $r->getRuleConfig(),
            'is_active' => $r->isActive(),
        ], $orderRules);

        return new JsonResponse(ApiEnvelope::wrap($data));
    }

    #[Route('/delivery-zones/{id}', name: 'api_delivery_zones_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $zone = $this->zoneService->findById($id);

        if ($zone === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Delivery zone not found.'),
                404,
            );
        }

        $this->denyAccessUnlessGranted(Permission::ZONE_EDIT, $zone);

        // Optimistic concurrency via If-Match header.
        $ifMatch = $request->headers->get('If-Match');

        if ($ifMatch === null) {
            return new JsonResponse(
                ErrorEnvelope::create('MISSING_IF_MATCH', 'If-Match header is required for updates.'),
                428,
            );
        }

        $expectedVersion = (int) trim($ifMatch, '"');

        if ($expectedVersion !== $zone->getVersion()) {
            return new JsonResponse(
                ErrorEnvelope::create('CONFLICT', 'The resource has been modified by another request.', [
                    'current_version' => $zone->getVersion(),
                ]),
                409,
            );
        }

        $body = json_decode($request->getContent(), true);

        if (!is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        /** @var User $actor */
        $actor = $this->getUser();

        try {
            $zone = $this->zoneService->update($zone, $body, $actor);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                422,
            );
        } catch (\Doctrine\ORM\OptimisticLockException) {
            return new JsonResponse(
                ErrorEnvelope::create('CONFLICT', 'The resource has been modified by another request.'),
                409,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeZone($zone)));
    }

    /* -------------------------------------------------------------- */
    /*  Zone Mapping Endpoints                                         */
    /* -------------------------------------------------------------- */

    #[Route('/delivery-zones/{zoneId}/mappings', name: 'api_zone_mappings_create', methods: ['POST'])]
    public function createMapping(string $zoneId, Request $request): JsonResponse
    {
        $zone = $this->zoneService->findById($zoneId);
        if ($zone === null) {
            return new JsonResponse(ErrorEnvelope::create('NOT_FOUND', 'Delivery zone not found.'), 404);
        }

        // Subject-aware permission check on the concrete zone.
        $this->denyAccessUnlessGranted(Permission::ZONE_EDIT, $zone);

        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return new JsonResponse(ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'), 400);
        }

        $mappingType = $body['mapping_type'] ?? null;
        $mappedEntityId = $body['mapped_entity_id'] ?? null;
        $precedence = (int) ($body['precedence'] ?? 0);

        if (!in_array($mappingType, ['administrative_area', 'community_grid'], true)) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'mapping_type must be "administrative_area" or "community_grid".'),
                422,
            );
        }
        if (!is_string($mappedEntityId) || $mappedEntityId === '') {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'mapped_entity_id is required.'),
                422,
            );
        }

        // Validate UUID format before parsing.
        try {
            $parsedUuid = \Symfony\Component\Uid\Uuid::fromString($mappedEntityId);
        } catch (\Symfony\Component\Uid\Exception\InvalidArgumentException) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'mapped_entity_id must be a valid UUID.'),
                422,
            );
        }

        // Check for duplicate mapping
        $existing = $this->entityManager->getRepository(\App\Entity\ZoneMapping::class)->findOneBy([
            'zone' => $zone,
            'mappingType' => $mappingType,
            'mappedEntityId' => $parsedUuid->toBinary(),
        ]);
        if ($existing !== null) {
            return new JsonResponse(
                ErrorEnvelope::create('CONFLICT', 'This mapping already exists for this zone.'),
                409,
            );
        }

        $mapping = new \App\Entity\ZoneMapping();
        $mapping->setZone($zone);
        $mapping->setMappingType($mappingType);
        $mapping->setMappedEntityId($parsedUuid->toBinary());
        $mapping->setPrecedence($precedence);

        $this->entityManager->persist($mapping);
        $this->entityManager->flush();

        return new JsonResponse(ApiEnvelope::wrap([
            'id' => $mapping->getId()->toRfc4122(),
            'zone_id' => $zone->getId()->toRfc4122(),
            'mapping_type' => $mapping->getMappingType(),
            'mapped_entity_id' => $mappedEntityId,
            'precedence' => $mapping->getPrecedence(),
        ]), 201);
    }

    #[Route('/delivery-zones/{zoneId}/mappings', name: 'api_zone_mappings_list', methods: ['GET'])]
    public function listMappings(string $zoneId): JsonResponse
    {
        $zone = $this->zoneService->findById($zoneId);
        if ($zone === null) {
            return new JsonResponse(ErrorEnvelope::create('NOT_FOUND', 'Delivery zone not found.'), 404);
        }

        // Subject-aware permission check on the concrete zone.
        $this->denyAccessUnlessGranted(Permission::ZONE_VIEW, $zone);

        $mappings = $this->entityManager->getRepository(\App\Entity\ZoneMapping::class)->findBy(
            ['zone' => $zone],
            ['precedence' => 'ASC'],
        );

        $data = array_map(static fn (\App\Entity\ZoneMapping $m) => [
            'id' => $m->getId()->toRfc4122(),
            'zone_id' => $zone->getId()->toRfc4122(),
            'mapping_type' => $m->getMappingType(),
            'mapped_entity_id' => \Symfony\Component\Uid\Uuid::fromBinary($m->getMappedEntityId())->toRfc4122(),
            'precedence' => $m->getPrecedence(),
            'created_at' => $m->getCreatedAt()->format('c'),
        ], $mappings);

        return new JsonResponse(ApiEnvelope::wrap($data));
    }

    /* -------------------------------------------------------------- */
    /*  Serializer                                                     */
    /* -------------------------------------------------------------- */

    /**
     * @return array<string, mixed>
     */
    private function serializeZone(DeliveryZone $zone): array
    {
        return [
            'id' => $zone->getId()->toRfc4122(),
            'store_id' => $zone->getStore()->getId()->toRfc4122(),
            'name' => $zone->getName(),
            'status' => $zone->getStatus(),
            'min_order_threshold' => $zone->getMinOrderThreshold(),
            'delivery_fee' => $zone->getDeliveryFee(),
            'is_active' => $zone->isActive(),
            'created_at' => $zone->getCreatedAt()->format('c'),
            'updated_at' => $zone->getUpdatedAt()->format('c'),
            'version' => $zone->getVersion(),
        ];
    }
}
