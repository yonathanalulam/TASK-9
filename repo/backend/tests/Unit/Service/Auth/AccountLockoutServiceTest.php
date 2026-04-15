<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Auth;

use App\Entity\User;
use App\Service\Auth\AccountLockoutService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountLockoutService::class)]
final class AccountLockoutServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private AccountLockoutService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new AccountLockoutService($this->entityManager);
    }

    public function testRecordFailedAttemptIncrementsCounter(): void
    {
        $user = $this->createUser();

        $this->entityManager->expects(self::once())->method('flush');

        self::assertSame(0, $user->getFailedLoginAttempts());

        $this->service->recordFailedAttempt($user);

        self::assertSame(1, $user->getFailedLoginAttempts());
    }

    public function testFiveFailuresTriggersLock(): void
    {
        $user = $this->createUser();
        $user->setFailedLoginAttempts(4); // Next attempt will be the 5th.

        $this->entityManager->expects(self::once())->method('flush');

        $this->service->recordFailedAttempt($user);

        self::assertSame(5, $user->getFailedLoginAttempts());
        self::assertNotNull($user->getLockedUntil());

        // The lock should be approximately 30 minutes in the future.
        $expectedMinimum = new \DateTimeImmutable('+29 minutes');
        $expectedMaximum = new \DateTimeImmutable('+31 minutes');
        self::assertGreaterThanOrEqual($expectedMinimum, $user->getLockedUntil());
        self::assertLessThanOrEqual($expectedMaximum, $user->getLockedUntil());
    }

    public function testIsLockedReturnsTrueWhenLockedUntilIsInFuture(): void
    {
        $user = $this->createUser();
        $user->setLockedUntil(new \DateTimeImmutable('+15 minutes'));

        self::assertTrue($this->service->isLocked($user));
    }

    public function testIsLockedReturnsFalseWhenLockedUntilIsInPast(): void
    {
        $user = $this->createUser();
        $user->setLockedUntil(new \DateTimeImmutable('-1 minute'));

        self::assertFalse($this->service->isLocked($user));
    }

    public function testIsLockedReturnsFalseWhenNoLockIsSet(): void
    {
        $user = $this->createUser();

        self::assertFalse($this->service->isLocked($user));
    }

    public function testResetAttemptsClearsCounterAndLock(): void
    {
        $user = $this->createUser();
        $user->setFailedLoginAttempts(5);
        $user->setLockedUntil(new \DateTimeImmutable('+30 minutes'));

        $this->entityManager->expects(self::once())->method('flush');

        $this->service->resetAttempts($user);

        self::assertSame(0, $user->getFailedLoginAttempts());
        self::assertNull($user->getLockedUntil());
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setDisplayName('Test User');
        $user->setPasswordHash('$2y$04$hashed');

        return $user;
    }
}
