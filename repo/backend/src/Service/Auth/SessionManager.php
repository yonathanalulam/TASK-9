<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Session;
use App\Entity\User;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;

class SessionManager
{
    /** Idle timeout in seconds (30 minutes). */
    private const int IDLE_TIMEOUT = 1800;

    /** Absolute maximum session age in seconds (12 hours). */
    private const int MAX_AGE = 43200;

    /** Number of random bytes used to generate a session token. */
    private const int TOKEN_BYTES = 64;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SessionRepository $sessionRepository,
    ) {
    }

    /**
     * Create a new session for the given user and return the raw bearer token.
     */
    public function createSession(User $user, ?string $ipAddress = null, ?string $userAgent = null): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));

        $session = new Session();
        $session->setUser($user);
        $session->setTokenHash(hash('sha256', $token));
        $session->setIpAddress($ipAddress);
        $session->setUserAgent($userAgent);
        $session->setExpiresAt(new \DateTimeImmutable(sprintf('+%d seconds', self::MAX_AGE)));

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $token;
    }

    /**
     * Validate a raw bearer token and return the matching session, or null if invalid.
     *
     * On success the session's lastActivityAt is refreshed automatically.
     */
    public function validateToken(string $token): ?Session
    {
        $hash = hash('sha256', $token);
        $session = $this->sessionRepository->findOneBy(['tokenHash' => $hash]);

        if ($session === null) {
            return null;
        }

        $now = new \DateTimeImmutable();

        if ($session->getRevokedAt() !== null) {
            return null;
        }

        if ($session->getExpiresAt() < $now) {
            return null;
        }

        $idleDeadline = $session->getLastActivityAt()->modify(sprintf('+%d seconds', self::IDLE_TIMEOUT));
        if ($idleDeadline < $now) {
            return null;
        }

        $session->setLastActivityAt($now);
        $this->entityManager->flush();

        return $session;
    }

    /**
     * Revoke a single session.
     */
    public function revokeSession(Session $session, string $reason = 'logout'): void
    {
        $session->setRevokedAt(new \DateTimeImmutable());
        $session->setRevocationReason($reason);

        $this->entityManager->flush();
    }

    /**
     * Revoke all active sessions for a user, optionally excluding one session.
     */
    public function revokeAllForUser(User $user, string $reason, ?Session $exceptSession = null): void
    {
        $sessions = $this->sessionRepository->findBy([
            'user' => $user,
        ]);

        foreach ($sessions as $session) {
            if ($session->getRevokedAt() !== null) {
                continue;
            }

            if ($exceptSession !== null && $session->getId()->equals($exceptSession->getId())) {
                continue;
            }

            $session->setRevokedAt(new \DateTimeImmutable());
            $session->setRevocationReason($reason);
        }

        $this->entityManager->flush();
    }
}
