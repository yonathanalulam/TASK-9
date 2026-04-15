<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Entity\ConsentRecord;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Security\Permission;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/consent')]
class ConsentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'api_consent_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::COMPLIANCE_MANAGE);

        $body = json_decode($request->getContent(), true);

        if (!\is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        $userId = $body['user_id'] ?? null;
        $consentType = $body['consent_type'] ?? null;
        $consentScope = $body['consent_scope'] ?? null;
        $granted = $body['granted'] ?? null;

        $errors = [];

        if (!\is_string($userId) || $userId === '') {
            $errors['user_id'] = 'user_id is required.';
        }

        if (!\is_string($consentType) || $consentType === '') {
            $errors['consent_type'] = 'consent_type is required.';
        }

        if (!\is_string($consentScope) || $consentScope === '') {
            $errors['consent_scope'] = 'consent_scope is required.';
        }

        if (!\is_bool($granted)) {
            $errors['granted'] = 'granted must be a boolean.';
        }

        if ($errors !== []) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'Validation failed.', $errors),
                422,
            );
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);

        if ($user === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'User not found.'),
                404,
            );
        }

        $record = new ConsentRecord(
            user: $user,
            consentType: $consentType,
            consentScope: $consentScope,
            granted: $granted,
            ipAddress: $request->getClientIp(),
            userAgent: $request->headers->get('User-Agent'),
        );

        $this->entityManager->persist($record);
        $this->entityManager->flush();

        return new JsonResponse(ApiEnvelope::wrap($this->serializeConsentRecord($record)), 201);
    }

    #[Route('/user/{userId}', name: 'api_consent_user_history', methods: ['GET'])]
    public function userHistory(string $userId): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::COMPLIANCE_VIEW);

        $user = $this->entityManager->getRepository(User::class)->find($userId);

        if ($user === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'User not found.'),
                404,
            );
        }

        $records = $this->entityManager->getRepository(ConsentRecord::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
        );

        $data = array_map(
            fn (ConsentRecord $record) => $this->serializeConsentRecord($record),
            $records,
        );

        return new JsonResponse(ApiEnvelope::wrap($data));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeConsentRecord(ConsentRecord $record): array
    {
        return [
            'id' => $record->getId()->toRfc4122(),
            'user_id' => $record->getUser()->getId()->toRfc4122(),
            'consent_type' => $record->getConsentType(),
            'consent_scope' => $record->getConsentScope(),
            'granted' => $record->isGranted(),
            'ip_address' => $record->getIpAddress(),
            'user_agent' => $record->getUserAgent(),
            'created_at' => $record->getCreatedAt()->format('c'),
        ];
    }
}
