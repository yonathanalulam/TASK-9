<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Entity\DeliveryWindow;
use App\Entity\User;
use App\Repository\DeliveryWindowRepository;
use App\Repository\DeliveryZoneRepository;
use App\Security\Permission;
use App\Service\DeliveryZone\DeliveryWindowService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
class DeliveryWindowController extends AbstractController
{
    public function __construct(
        private readonly DeliveryWindowService $windowService,
        private readonly DeliveryZoneRepository $zoneRepository,
        private readonly DeliveryWindowRepository $windowRepository,
    ) {
    }

    #[Route('/delivery-zones/{zoneId}/windows', name: 'api_delivery_windows_create', methods: ['POST'])]
    public function create(string $zoneId, Request $request): JsonResponse
    {
        $zone = $this->zoneRepository->find($zoneId);

        if ($zone === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Delivery zone not found.'),
                404,
            );
        }

        $this->denyAccessUnlessGranted(Permission::ZONE_EDIT, $zone);

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
            $window = $this->windowService->create($body, $zoneId, $actor);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                422,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeWindow($window)), 201);
    }

    #[Route('/delivery-zones/{zoneId}/windows', name: 'api_delivery_windows_list', methods: ['GET'])]
    public function list(string $zoneId): JsonResponse
    {
        $zone = $this->zoneRepository->find($zoneId);

        if ($zone === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Delivery zone not found.'),
                404,
            );
        }

        $windows = $this->windowService->listForZone($zoneId);

        $data = array_map(
            fn (DeliveryWindow $w) => $this->serializeWindow($w),
            $windows,
        );

        return new JsonResponse(ApiEnvelope::wrap($data));
    }

    #[Route('/delivery-windows/{id}', name: 'api_delivery_windows_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $window = $this->windowRepository->find($id);

        if ($window === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Delivery window not found.'),
                404,
            );
        }

        $this->denyAccessUnlessGranted(Permission::ZONE_EDIT, $window->getZone());

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
            $window = $this->windowService->update($window, $body, $actor);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', $e->getMessage()),
                422,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap($this->serializeWindow($window)));
    }

    #[Route('/delivery-windows/{id}', name: 'api_delivery_windows_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $window = $this->windowRepository->find($id);

        if ($window === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Delivery window not found.'),
                404,
            );
        }

        $this->denyAccessUnlessGranted(Permission::ZONE_EDIT, $window->getZone());

        /** @var User $actor */
        $actor = $this->getUser();

        $this->windowService->deactivate($window, $actor);

        return new JsonResponse(ApiEnvelope::wrap([
            'message' => 'Delivery window deactivated successfully.',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWindow(DeliveryWindow $window): array
    {
        return [
            'id' => $window->getId()->toRfc4122(),
            'zone_id' => $window->getZone()->getId()->toRfc4122(),
            'day_of_week' => $window->getDayOfWeek(),
            'start_time' => $window->getStartTime()->format('H:i'),
            'end_time' => $window->getEndTime()->format('H:i'),
            'is_active' => $window->isActive(),
            'created_at' => $window->getCreatedAt()->format('c'),
            'updated_at' => $window->getUpdatedAt()->format('c'),
        ];
    }
}
