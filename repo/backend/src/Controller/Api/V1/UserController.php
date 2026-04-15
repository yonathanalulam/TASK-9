<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Dto\Response\PaginatedEnvelope;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Permission;
use App\Service\Auth\PasswordPolicyService;
use App\Service\Auth\SessionManager;
use App\Service\Authorization\RbacService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/users')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PasswordPolicyService $passwordPolicyService,
        private readonly RbacService $rbacService,
        private readonly SessionManager $sessionManager,
    ) {
    }

    #[Route('', name: 'api_users_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::USER_CREATE);

        $body = json_decode($request->getContent(), true);

        if (!\is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        $username = $body['username'] ?? null;
        $displayName = $body['display_name'] ?? null;
        $password = $body['password'] ?? null;

        $errors = [];
        if (!\is_string($username) || $username === '') {
            $errors['username'] = 'Username is required.';
        }
        if (!\is_string($displayName) || $displayName === '') {
            $errors['display_name'] = 'Display name is required.';
        }
        if (!\is_string($password) || $password === '') {
            $errors['password'] = 'Password is required.';
        }

        if ($errors !== []) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'Validation failed.', $errors),
                422,
            );
        }

        // Check for duplicate username.
        $existing = $this->userRepository->findOneBy(['username' => $username]);
        if ($existing !== null) {
            return new JsonResponse(
                ErrorEnvelope::create('DUPLICATE_USERNAME', 'A user with this username already exists.', [
                    'username' => 'Username is already taken.',
                ]),
                409,
            );
        }

        // Validate password policy.
        $policyErrors = $this->passwordPolicyService->validate($password);
        if ($policyErrors !== []) {
            return new JsonResponse(
                ErrorEnvelope::create('PASSWORD_POLICY_VIOLATION', 'Password does not meet policy requirements.', [
                    'password' => $policyErrors,
                ]),
                422,
            );
        }

        $user = new User();
        $user->setUsername($username);
        $user->setDisplayName($displayName);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPasswordHash($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse(ApiEnvelope::wrap([
            'id' => $user->getId()->toRfc4122(),
            'username' => $user->getUsername(),
            'display_name' => $user->getDisplayName(),
            'status' => $user->getStatus(),
            'created_at' => $user->getCreatedAt()->format('c'),
            'updated_at' => $user->getUpdatedAt()->format('c'),
        ]), 201);
    }

    #[Route('', name: 'api_users_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::USER_VIEW);

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));

        $total = (int) $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $users = $this->userRepository->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $data = array_map(static fn (User $user) => [
            'id' => $user->getId()->toRfc4122(),
            'username' => $user->getUsername(),
            'display_name' => $user->getDisplayName(),
            'status' => $user->getStatus(),
            'last_login_at' => $user->getLastLoginAt()?->format('c'),
            'created_at' => $user->getCreatedAt()->format('c'),
            'updated_at' => $user->getUpdatedAt()->format('c'),
        ], $users);

        return new JsonResponse(PaginatedEnvelope::wrap($data, $page, $perPage, $total));
    }

    #[Route('/{id}', name: 'api_users_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::USER_VIEW);

        $user = $this->userRepository->find($id);

        if ($user === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'User not found.'),
                404,
            );
        }

        $assignments = $this->rbacService->getEffectiveAssignments($user);

        $roles = array_map(static fn ($assignment) => [
            'id' => $assignment->getId()->toRfc4122(),
            'role' => $assignment->getRole()->getName(),
            'role_display_name' => $assignment->getRole()->getDisplayName(),
            'scope_type' => $assignment->getScopeType()->value,
            'scope_id' => $assignment->getScopeId(),
            'effective_from' => $assignment->getEffectiveFrom()->format('Y-m-d'),
            'effective_until' => $assignment->getEffectiveUntil()?->format('Y-m-d'),
            'granted_at' => $assignment->getGrantedAt()->format('c'),
        ], $assignments);

        return new JsonResponse(ApiEnvelope::wrap([
            'id' => $user->getId()->toRfc4122(),
            'username' => $user->getUsername(),
            'display_name' => $user->getDisplayName(),
            'status' => $user->getStatus(),
            'last_login_at' => $user->getLastLoginAt()?->format('c'),
            'created_at' => $user->getCreatedAt()->format('c'),
            'updated_at' => $user->getUpdatedAt()->format('c'),
            'version' => $user->getVersion(),
            'roles' => $roles,
        ]));
    }

    #[Route('/{id}', name: 'api_users_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::USER_EDIT);

        $user = $this->userRepository->find($id);

        if ($user === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'User not found.'),
                404,
            );
        }

        // Optimistic concurrency check via If-Match header.
        $ifMatch = $request->headers->get('If-Match');

        if ($ifMatch === null) {
            return new JsonResponse(
                ErrorEnvelope::create('MISSING_IF_MATCH', 'If-Match header is required for updates.'),
                428,
            );
        }

        $expectedVersion = (int) trim($ifMatch, '"');

        if ($expectedVersion !== $user->getVersion()) {
            return new JsonResponse(
                ErrorEnvelope::create('CONFLICT', 'The resource has been modified by another request.', [
                    'current_version' => $user->getVersion(),
                ]),
                409,
            );
        }

        $body = json_decode($request->getContent(), true);

        if (!\is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        $errors = [];

        if (\array_key_exists('display_name', $body)) {
            if (!\is_string($body['display_name']) || $body['display_name'] === '') {
                $errors['display_name'] = 'Display name must be a non-empty string.';
            } else {
                $user->setDisplayName($body['display_name']);
            }
        }

        if (\array_key_exists('status', $body)) {
            $allowedStatuses = ['ACTIVE', 'INACTIVE', 'SUSPENDED'];
            if (!\is_string($body['status']) || !\in_array($body['status'], $allowedStatuses, true)) {
                $errors['status'] = 'Status must be one of: ACTIVE, INACTIVE, SUSPENDED.';
            } else {
                $user->setStatus($body['status']);
            }
        }

        if ($errors !== []) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'Validation failed.', $errors),
                422,
            );
        }

        $user->setUpdatedAt(new \DateTimeImmutable());

        try {
            $this->entityManager->flush();
        } catch (\Doctrine\ORM\OptimisticLockException) {
            return new JsonResponse(
                ErrorEnvelope::create('CONFLICT', 'The resource has been modified by another request.'),
                409,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap([
            'id' => $user->getId()->toRfc4122(),
            'username' => $user->getUsername(),
            'display_name' => $user->getDisplayName(),
            'status' => $user->getStatus(),
            'last_login_at' => $user->getLastLoginAt()?->format('c'),
            'created_at' => $user->getCreatedAt()->format('c'),
            'updated_at' => $user->getUpdatedAt()->format('c'),
            'version' => $user->getVersion(),
        ]));
    }

    #[Route('/{id}/deactivate', name: 'api_users_deactivate', methods: ['PATCH'])]
    public function deactivate(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::USER_DEACTIVATE);

        $user = $this->userRepository->find($id);

        if ($user === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'User not found.'),
                404,
            );
        }

        if ($user->getStatus() === 'INACTIVE') {
            return new JsonResponse(
                ErrorEnvelope::create('ALREADY_INACTIVE', 'User is already inactive.'),
                409,
            );
        }

        $user->setStatus('INACTIVE');
        $user->setUpdatedAt(new \DateTimeImmutable());

        $this->sessionManager->revokeAllForUser($user, 'account_deactivated');

        $this->entityManager->flush();

        return new JsonResponse(ApiEnvelope::wrap([
            'id' => $user->getId()->toRfc4122(),
            'username' => $user->getUsername(),
            'display_name' => $user->getDisplayName(),
            'status' => $user->getStatus(),
            'updated_at' => $user->getUpdatedAt()->format('c'),
            'message' => 'User deactivated successfully. All sessions have been revoked.',
        ]));
    }
}
