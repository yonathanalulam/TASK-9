<?php

declare(strict_types=1);

namespace App\Service\Authorization;

use App\Entity\User;
use App\Entity\UserRoleAssignment;
use App\Enum\RoleName;
use App\Enum\ScopeType;
use App\Repository\UserRoleAssignmentRepository;
use Doctrine\ORM\EntityManagerInterface;

class RbacService
{
    public function __construct(
        private readonly UserRoleAssignmentRepository $assignmentRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Returns all effective (non-revoked, currently active) role assignments for a user.
     *
     * @return UserRoleAssignment[]
     */
    public function getEffectiveAssignments(User $user, ?\DateTimeImmutable $at = null): array
    {
        $at ??= new \DateTimeImmutable();

        $qb = $this->assignmentRepository->createQueryBuilder('ura')
            ->join('ura.user', 'u')
            ->where('u.id = :userId')
            ->andWhere('ura.revokedAt IS NULL')
            ->andWhere('ura.effectiveFrom <= :at')
            ->andWhere('(ura.effectiveUntil IS NULL OR ura.effectiveUntil >= :at)')
            ->setParameter('userId', $user->getId(), 'uuid')
            ->setParameter('at', $at);

        return $qb->getQuery()->getResult();
    }

    /**
     * Checks whether a user holds a specific role, optionally within a given scope.
     *
     * A GLOBAL-scoped assignment always satisfies any scope check.
     */
    public function hasRole(
        User $user,
        RoleName $roleName,
        ?ScopeType $scopeType = null,
        ?string $scopeId = null,
    ): bool {
        $assignments = $this->getEffectiveAssignments($user);

        foreach ($assignments as $assignment) {
            if ($assignment->getRole()->getName() !== $roleName->value) {
                continue;
            }

            // No scope filter requested -- any matching role is sufficient.
            if ($scopeType === null) {
                return true;
            }

            // GLOBAL scope always satisfies any scope check.
            if ($assignment->getScopeType() === ScopeType::GLOBAL) {
                return true;
            }

            // Exact scope match.
            if ($assignment->getScopeType() === $scopeType) {
                if ($scopeId === null) {
                    return true;
                }

                if ($assignment->getScopeId() === $scopeId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns true if the user holds any of the given roles (effective right now).
     *
     * @param RoleName[] $roleNames
     */
    public function hasAnyRole(User $user, array $roleNames): bool
    {
        $assignments = $this->getEffectiveAssignments($user);
        $nameValues = array_map(static fn (RoleName $r): string => $r->value, $roleNames);

        foreach ($assignments as $assignment) {
            if (\in_array($assignment->getRole()->getName(), $nameValues, true)) {
                return true;
            }
        }

        return false;
    }
}
