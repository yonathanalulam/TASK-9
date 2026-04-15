<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\Entity\Session;
use App\Entity\User;
use App\Service\Auth\AccountLockoutService;
use App\Service\Auth\AuthenticationService;
use App\Service\Auth\SessionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class AuthenticationFlowTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private AuthenticationService $authService;
    private SessionManager $sessionManager;
    private AccountLockoutService $lockoutService;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->authService = $container->get(AuthenticationService::class);
        $this->sessionManager = $container->get(SessionManager::class);
        $this->lockoutService = $container->get(AccountLockoutService::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
    }

    public function testSuccessfulLoginCreatesSessionAndReturnsToken(): void
    {
        $user = $this->createTestUser('login_ok', 'V@lid1Password!');

        $result = $this->authService->login('login_ok', 'V@lid1Password!');

        self::assertArrayHasKey('token', $result);
        self::assertArrayHasKey('user', $result);
        self::assertNotEmpty($result['token']);
        self::assertInstanceOf(User::class, $result['user']);
        self::assertSame('login_ok', $result['user']->getUsername());
    }

    public function testFailedLoginIncrementsAttemptCounter(): void
    {
        $user = $this->createTestUser('login_fail', 'V@lid1Password!');

        try {
            $this->authService->login('login_fail', 'wrong-password');
        } catch (AuthenticationException) {
            // Expected.
        }

        $this->em->refresh($user);
        self::assertSame(1, $user->getFailedLoginAttempts());
    }

    public function testFiveFailuresLockTheAccount(): void
    {
        $user = $this->createTestUser('lock_test', 'V@lid1Password!');

        for ($i = 0; $i < 5; $i++) {
            try {
                $this->authService->login('lock_test', 'wrong-password');
            } catch (AuthenticationException|TooManyRequestsHttpException) {
                // Expected.
            }
        }

        $this->em->refresh($user);
        self::assertSame(5, $user->getFailedLoginAttempts());
        self::assertNotNull($user->getLockedUntil());
        self::assertTrue($this->lockoutService->isLocked($user));
    }

    public function testLockedAccountReturnsTooManyRequestsException(): void
    {
        $user = $this->createTestUser('locked_user', 'V@lid1Password!');

        // Manually lock the account.
        $user->setFailedLoginAttempts(5);
        $user->setLockedUntil(new \DateTimeImmutable('+30 minutes'));
        $this->em->flush();

        $this->expectException(TooManyRequestsHttpException::class);

        $this->authService->login('locked_user', 'V@lid1Password!');
    }

    public function testSessionValidatesCorrectlyAfterLogin(): void
    {
        $this->createTestUser('session_ok', 'V@lid1Password!');

        $result = $this->authService->login('session_ok', 'V@lid1Password!');
        $token = $result['token'];

        $session = $this->sessionManager->validateToken($token);

        self::assertInstanceOf(Session::class, $session);
        self::assertSame('session_ok', $session->getUser()->getUsername());
    }

    public function testSessionIdleTimeout(): void
    {
        $this->createTestUser('idle_test', 'V@lid1Password!');

        $result = $this->authService->login('idle_test', 'V@lid1Password!');
        $token = $result['token'];

        // Modify the session's lastActivityAt to be > 30 minutes ago.
        $tokenHash = hash('sha256', $token);
        $session = $this->em->getRepository(Session::class)->findOneBy(['tokenHash' => $tokenHash]);
        self::assertNotNull($session);

        $session->setLastActivityAt(new \DateTimeImmutable('-31 minutes'));
        $this->em->flush();

        $validatedSession = $this->sessionManager->validateToken($token);

        self::assertNull($validatedSession, 'Session should be invalid after idle timeout.');
    }

    public function testSessionMaxAge(): void
    {
        $this->createTestUser('maxage_test', 'V@lid1Password!');

        $result = $this->authService->login('maxage_test', 'V@lid1Password!');
        $token = $result['token'];

        // Modify the session's expiresAt to be in the past (simulating > 12 hr age).
        $tokenHash = hash('sha256', $token);
        $session = $this->em->getRepository(Session::class)->findOneBy(['tokenHash' => $tokenHash]);
        self::assertNotNull($session);

        $session->setExpiresAt(new \DateTimeImmutable('-1 minute'));
        $this->em->flush();

        $validatedSession = $this->sessionManager->validateToken($token);

        self::assertNull($validatedSession, 'Session should be invalid after max age exceeded.');
    }

    public function testRevokeAllForUserRevokesAllSessions(): void
    {
        $user = $this->createTestUser('revoke_all', 'V@lid1Password!');

        // Create multiple sessions.
        $result1 = $this->authService->login('revoke_all', 'V@lid1Password!');
        $result2 = $this->authService->login('revoke_all', 'V@lid1Password!');

        $token1 = $result1['token'];
        $token2 = $result2['token'];

        // Both should be valid.
        self::assertNotNull($this->sessionManager->validateToken($token1));
        self::assertNotNull($this->sessionManager->validateToken($token2));

        // Revoke all.
        $this->em->refresh($user);
        $this->sessionManager->revokeAllForUser($user, 'test_revoke');

        // Both should now be invalid.
        self::assertNull($this->sessionManager->validateToken($token1));
        self::assertNull($this->sessionManager->validateToken($token2));
    }

    private function createTestUser(string $username, string $plainPassword): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setDisplayName('Test User ' . $username);
        $user->setStatus('ACTIVE');

        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPasswordHash($hashedPassword);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
