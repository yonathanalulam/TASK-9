<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Entity\User;
use App\Entity\UserRoleAssignment;
use App\Enum\RoleName;
use App\Enum\ScopeType;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Repository\UserRoleAssignmentRepository;
use App\Security\Permission;
use App\Service\Auth\SessionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/users/{userId}/role-assignments')]
class RoleAssignmentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly RoleRepository $roleRepository,
        private readonly UserRoleAssignmentRepository $assignmentRepository,
        private readonly SessionManager $sessionManager,
    ) {
    }

    #[Route('', name: 'api_role_assignments_create', methods: ['POST'])]
    public function assign(string $userId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ROLE_ASSIGN);

        $targetUser = $this->userRepository->find($userId);

        if ($targetUser === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'User not found.'),
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

        $roleName = $body['role_name'] ?? null;
        $scopeTypeValue = $body['scope_type'] ?? null;
        $scopeId = $body['scope_id'] ?? null;
        $effectiveFromRaw = $body['effective_from'] ?? null;
        $effectiveUntilRaw = $body['effective_until'] ?? null;

        $errors = [];

        // Validate role_name against RoleName enum.
        $roleNameEnum = null;
        if (!\is_string($roleName) || $roleName === '') {
            $errors['role_name'] = 'Role name is required.';
        } else {
            $roleNameEnum = RoleName::tryFrom($roleName);
            if ($roleNameEnum === null) {
                $validValues = array_map(
                    static fn (RoleName $r): string => $r->value,
                    RoleName::cases(),
                );
                $errors['role_name'] = sprintf(
                    'Invalid role name. Must be one of: %s.',
                    implode(', ', $validValues),
                );
            }
        }

        // Validate scope_type against ScopeType enum.
        $scopeTypeEnum = null;
        if (!\is_string($scopeTypeValue) || $scopeTypeValue === '') {
            $errors['scope_type'] = 'Scope type is required.';
        } else {
            $scopeTypeEnum = ScopeType::tryFrom($scopeTypeValue);
            if ($scopeTypeEnum === null) {
                $validValues = array_map(
                    static fn (ScopeType $s): string => $s->value,
                    ScopeType::cases(),
                );
                $errors['scope_type'] = sprintf(
                    'Invalid scope type. Must be one of: %s.',
                    implode(', ', $validValues),
                );
            }
        }

        // Validate effective_from.
        $effectiveFrom = null;
        if (!\is_string($effectiveFromRaw) || $effectiveFromRaw === '') {
            $errors['effective_from'] = 'Effective from date is required.';
        } else {
            try {
                $effectiveFrom = new \DateTimeImmutable($effectiveFromRaw);
            } catch (\Exception) {
                $errors['effective_from'] = 'Invalid date format for effective_from.';
            }
        }

        // Validate effective_until (optional).
        $effectiveUntil = null;
        if ($effectiveUntilRaw !== null && $effectiveUntilRaw !== '') {
            if (!\is_string($effectiveUntilRaw)) {
                $errors['effective_until'] = 'effective_until must be a date string.';
            } else {
                try {
                    $effectiveUntil = new \DateTimeImmutable($effectiveUntilRaw);
                } catch (\Exception) {
                    $errors['effective_until'] = 'Invalid date format for effective_until.';
                }
            }
        }

        // Validate scope_id is required for non-GLOBAL scopes.
        if ($scopeTypeEnum !== null && $scopeTypeEnum !== ScopeType::GLOBAL && ($scopeId === null || $scopeId === '')) {
            $errors['scope_id'] = 'Scope ID is required for non-GLOBAL scope types.';
        }

        if ($errors !== []) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'Validation failed.', $errors),
                422,
            );
        }

        // Look up the Role entity by name.
        $role = $this->roleRepository->findOneBy(['name' => $roleNameEnum->value]);

        if ($role === null) {
            return new JsonResponse(
                ErrorEnvelope::create('ROLE_NOT_FOUND', sprintf('Role "%s" does not exist in the system.', $roleNameEnum->value)),
                404,
            );
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $assignment = new UserRoleAssignment();
        $assignment->setUser($targetUser);
        $assignment->setRole($role);
        $assignment->setScopeType($scopeTypeEnum);
        $assignment->setScopeId($scopeTypeEnum === ScopeType::GLOBAL ? null : $scopeId);
        $assignment->setEffectiveFrom($effectiveFrom);
        $assignment->setEffectiveUntil($effectiveUntil);
        $assignment->setGrantedBy($currentUser);

        $this->entityManager->persist($assignment);
        $this->entityManager->flush();

        return new JsonResponse(ApiEnvelope::wrap([
            'id' => $assignment->getId()->toRfc4122(),
            'user_id' => $targetUser->getId()->toRfc4122(),
            'role' => $role->getName(),
            'role_display_name' => $role->getDisplayName(),
            'scope_type' => $assignment->getScopeType()->value,
            'scope_id' => $assignment->getScopeId(),
            'effective_from' => $assignment->getEffectiveFrom()->format('Y-m-d'),
            'effective_until' => $assignment->getEffectiveUntil()?->format('Y-m-d'),
            'granted_by' => $currentUser->getId()->toRfc4122(),
            'granted_at' => $assignment->getGrantedAt()->format('c'),
        ]), 201);
    }

    #[Route('', name: 'api_role_assignments_list', methods: ['GET'])]
    public function list(string $userId): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::USER_VIEW);

        $targetUser = $this->userRepository->find($userId);

        if ($targetUser === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'User not found.'),
                404,
            );
        }

        // Return all assignments including revoked ones (for audit trail).
        $assignments = $this->assignmentRepository->findBy(
            ['user' => $targetUser],
            ['grantedAt' => 'DESC'],
        );

        $data = array_map(static fn (UserRoleAssignment $assignment) => [
            'id' => $assignment->getId()->toRfc4122(),
            'role' => $assignment->getRole()->getName(),
            'role_display_name' => $assignment->getRole()->getDisplayName(),
            'scope_type' => $assignment->getScopeType()->value,
            'scope_id' => $assignment->getScopeId(),
            'effective_from' => $assignment->getEffectiveFrom()->format('Y-m-d'),
            'effective_until' => $assignment->getEffectiveUntil()?->format('Y-m-d'),
            'granted_by' => $assignment->getGrantedBy()->getId()->toRfc4122(),
            'granted_at' => $assignment->getGrantedAt()->format('c'),
            'revoked_at' => $assignment->getRevokedAt()?->format('c'),
            'revoked_by' => $assignment->getRevokedBy()?->getId()->toRfc4122(),
        ], $assignments);

        return new JsonResponse(ApiEnvelope::wrap($data));
    }

    #[Route('/{assignmentId}', name: 'api_role_assignments_revoke', methods: ['DELETE'])]
    public function revoke(string $userId, string $assignmentId): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ROLE_REVOKE);

        $targetUser = $this->userRepository->find($userId);

        if ($targetUser === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'User not found.'),
                404,
            );
        }

        $assignment = $this->assignmentRepository->find($assignmentId);

        if ($assignment === null) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Role assignment not found.'),
                404,
            );
        }

        // Verify the assignment belongs to the specified user.
        if ($assignment->getUser()->getId()->toRfc4122() !== $targetUser->getId()->toRfc4122()) {
            return new JsonResponse(
                ErrorEnvelope::create('NOT_FOUND', 'Role assignment not found for this user.'),
                404,
            );
        }

        if ($assignment->getRevokedAt() !== null) {
            return new JsonResponse(
                ErrorEnvelope::create('ALREADY_REVOKED', 'This role assignment has already been revoked.'),
                409,
            );
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $assignment->setRevokedAt(new \DateTimeImmutable());
        $assignment->setRevokedBy($currentUser);

        // Revoke all user sessions due to role change.
        $this->sessionManager->revokeAllForUser($targetUser, 'role_changed');

        $this->entityManager->flush();

        return new JsonResponse(ApiEnvelope::wrap([
            'id' => $assignment->getId()->toRfc4122(),
            'role' => $assignment->getRole()->getName(),
            'revoked_at' => $assignment->getRevokedAt()->format('c'),
            'revoked_by' => $currentUser->getId()->toRfc4122(),
            'message' => 'Role assignment revoked successfully. All user sessions have been invalidated.',
        ]));
    }
}
