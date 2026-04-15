<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class AccountLockoutService
{
    private const int MAX_FAILED_ATTEMPTS = 5;
    private const int LOCKOUT_DURATION_MINUTES = 30;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Record a failed login attempt. Locks the account after the threshold is reached.
     */
    public function recordFailedAttempt(User $user): void
    {
        $user->setFailedLoginAttempts($user->getFailedLoginAttempts() + 1);

        if ($user->getFailedLoginAttempts() >= self::MAX_FAILED_ATTEMPTS) {
            $user->setLockedUntil(
                new \DateTimeImmutable(sprintf('+%d minutes', self::LOCKOUT_DURATION_MINUTES)),
            );
        }

        $this->entityManager->flush();
    }

    /**
     * Check whether the account is currently locked.
     */
    public function isLocked(User $user): bool
    {
        $lockedUntil = $user->getLockedUntil();

        if ($lockedUntil === null) {
            return false;
        }

        return $lockedUntil > new \DateTimeImmutable();
    }

    /**
     * Reset the failed-attempt counter and clear any lockout.
     */
    public function resetAttempts(User $user): void
    {
        $user->setFailedLoginAttempts(0);
        $user->setLockedUntil(null);

        $this->entityManager->flush();
    }
}
