<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class AuthenticationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly PasswordPolicyService $passwordPolicyService,
        private readonly AccountLockoutService $accountLockoutService,
        private readonly SessionManager $sessionManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * Authenticate a user by username and password, returning a session token.
     *
     * @return array{token: string, user: User}
     *
     * @throws AuthenticationException
     * @throws TooManyRequestsHttpException
     */
    public function login(string $username, string $password, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $user = $this->userRepository->findOneBy(['username' => $username]);

        if ($user === null) {
            throw new AuthenticationException('Invalid credentials');
        }

        if ($user->getStatus() !== 'ACTIVE') {
            throw new AuthenticationException('Account is not active');
        }

        if ($this->accountLockoutService->isLocked($user)) {
            throw new TooManyRequestsHttpException(null, 'Account is locked. Try again later.');
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            $this->accountLockoutService->recordFailedAttempt($user);
            throw new AuthenticationException('Invalid credentials');
        }

        $this->accountLockoutService->resetAttempts($user);

        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $token = $this->sessionManager->createSession($user, $ipAddress, $userAgent);

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    /**
     * Change the authenticated user's password.
     *
     * @throws AuthenticationException  If the current password is incorrect
     * @throws \InvalidArgumentException If the new password violates the policy
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new AuthenticationException('Current password is incorrect');
        }

        $errors = $this->passwordPolicyService->validate($newPassword);
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPasswordHash($hashedPassword);
        $user->setPasswordChangedAt(new \DateTimeImmutable());

        $this->sessionManager->revokeAllForUser($user, 'password_changed');

        $this->entityManager->flush();
    }
}
