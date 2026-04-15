<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Response\ApiEnvelope;
use App\Dto\Response\ErrorEnvelope;
use App\Entity\User;
use App\Service\Auth\AuthenticationService;
use App\Service\Auth\SessionManager;
use App\Service\Authorization\RbacService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

#[Route('/api/v1/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly SessionManager $sessionManager,
        private readonly RbacService $rbacService,
    ) {
    }

    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        if (!\is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        $username = $body['username'] ?? null;
        $password = $body['password'] ?? null;

        $errors = [];
        if (!\is_string($username) || $username === '') {
            $errors['username'] = 'Username is required.';
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

        try {
            $result = $this->authenticationService->login(
                $username,
                $password,
                $request->getClientIp(),
                $request->headers->get('User-Agent'),
            );
        } catch (TooManyRequestsHttpException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('RATE_LIMITED', $e->getMessage()),
                429,
            );
        } catch (AuthenticationException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('AUTHENTICATION_FAILED', $e->getMessage()),
                401,
            );
        }

        /** @var User $user */
        $user = $result['user'];
        $assignments = $this->rbacService->getEffectiveAssignments($user);

        $roles = array_map(static fn ($assignment) => [
            'role' => $assignment->getRole()->getName(),
            'scope_type' => $assignment->getScopeType()->value,
            'scope_id' => $assignment->getScopeId(),
        ], $assignments);

        return new JsonResponse(ApiEnvelope::wrap([
            'token' => $result['token'],
            'user' => [
                'id' => $user->getId()->toRfc4122(),
                'username' => $user->getUsername(),
                'display_name' => $user->getDisplayName(),
                'status' => $user->getStatus(),
                'roles' => $roles,
            ],
        ]));
    }

    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if ($user === null) {
            return new JsonResponse(
                ErrorEnvelope::create('UNAUTHENTICATED', 'Authentication required.'),
                401,
            );
        }

        $authHeader = $request->headers->get('Authorization', '');
        $token = '';

        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
        }

        if ($token === '') {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_TOKEN', 'Bearer token is required.'),
                400,
            );
        }

        $session = $this->sessionManager->validateToken($token);

        if ($session === null) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_SESSION', 'Session not found or already expired.'),
                400,
            );
        }

        $this->sessionManager->revokeSession($session, 'logout');

        return new JsonResponse(ApiEnvelope::wrap([
            'message' => 'Logged out successfully.',
        ]));
    }

    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if ($user === null) {
            return new JsonResponse(
                ErrorEnvelope::create('UNAUTHENTICATED', 'Authentication required.'),
                401,
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
        ], $assignments);

        return new JsonResponse(ApiEnvelope::wrap([
            'id' => $user->getId()->toRfc4122(),
            'username' => $user->getUsername(),
            'display_name' => $user->getDisplayName(),
            'status' => $user->getStatus(),
            'last_login_at' => $user->getLastLoginAt()?->format('c'),
            'created_at' => $user->getCreatedAt()->format('c'),
            'updated_at' => $user->getUpdatedAt()->format('c'),
            'roles' => $roles,
        ]));
    }

    #[Route('/change-password', name: 'api_auth_change_password', methods: ['POST'])]
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if ($user === null) {
            return new JsonResponse(
                ErrorEnvelope::create('UNAUTHENTICATED', 'Authentication required.'),
                401,
            );
        }

        $body = json_decode($request->getContent(), true);

        if (!\is_array($body)) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_JSON', 'Request body must be valid JSON.'),
                400,
            );
        }

        $currentPassword = $body['current_password'] ?? null;
        $newPassword = $body['new_password'] ?? null;

        $errors = [];
        if (!\is_string($currentPassword) || $currentPassword === '') {
            $errors['current_password'] = 'Current password is required.';
        }
        if (!\is_string($newPassword) || $newPassword === '') {
            $errors['new_password'] = 'New password is required.';
        }

        if ($errors !== []) {
            return new JsonResponse(
                ErrorEnvelope::create('VALIDATION_ERROR', 'Validation failed.', $errors),
                422,
            );
        }

        try {
            $this->authenticationService->changePassword($user, $currentPassword, $newPassword);
        } catch (AuthenticationException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('INVALID_PASSWORD', $e->getMessage()),
                400,
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ErrorEnvelope::create('PASSWORD_POLICY_VIOLATION', $e->getMessage()),
                422,
            );
        }

        return new JsonResponse(ApiEnvelope::wrap([
            'message' => 'Password changed successfully.',
        ]));
    }
}
