<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Enum\RoleName;
use App\Security\Permission;
use App\Service\Authorization\RbacService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Authorises import and deduplication operations.
 *
 * Role mapping:
 *  - IMPORT_CREATE:  RECRUITER, OPERATIONS_ANALYST, ADMINISTRATOR.
 *  - IMPORT_VIEW:    RECRUITER, OPERATIONS_ANALYST, COMPLIANCE_OFFICER, ADMINISTRATOR.
 *  - DEDUP_REVIEW:   RECRUITER, OPERATIONS_ANALYST, ADMINISTRATOR.
 *  - DEDUP_MERGE:    RECRUITER, OPERATIONS_ANALYST, ADMINISTRATOR.
 *  - DEDUP_UNMERGE:  ADMINISTRATOR, COMPLIANCE_OFFICER only (destructive operation).
 *
 * @extends Voter<string, mixed>
 */
final class ImportVoter extends Voter
{
    private const array SUPPORTED_ATTRIBUTES = [
        Permission::IMPORT_CREATE,
        Permission::IMPORT_VIEW,
        Permission::DEDUP_REVIEW,
        Permission::DEDUP_MERGE,
        Permission::DEDUP_UNMERGE,
    ];

    public function __construct(
        private readonly RbacService $rbacService,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, self::SUPPORTED_ATTRIBUTES, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            Permission::IMPORT_CREATE => $this->canCreate($user),
            Permission::IMPORT_VIEW => $this->canView($user),
            Permission::DEDUP_REVIEW => $this->canReview($user),
            Permission::DEDUP_MERGE => $this->canMerge($user),
            Permission::DEDUP_UNMERGE => $this->canUnmerge($user),
            default => false,
        };
    }

    private function canCreate(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::RECRUITER,
            RoleName::OPERATIONS_ANALYST,
            RoleName::ADMINISTRATOR,
        ]);
    }

    private function canView(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::RECRUITER,
            RoleName::OPERATIONS_ANALYST,
            RoleName::COMPLIANCE_OFFICER,
            RoleName::ADMINISTRATOR,
        ]);
    }

    private function canReview(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::RECRUITER,
            RoleName::OPERATIONS_ANALYST,
            RoleName::ADMINISTRATOR,
        ]);
    }

    private function canMerge(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::RECRUITER,
            RoleName::OPERATIONS_ANALYST,
            RoleName::ADMINISTRATOR,
        ]);
    }

    /**
     * Unmerge is a destructive operation restricted to ADMINISTRATOR and
     * COMPLIANCE_OFFICER.
     */
    private function canUnmerge(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::ADMINISTRATOR,
            RoleName::COMPLIANCE_OFFICER,
        ]);
    }
}
