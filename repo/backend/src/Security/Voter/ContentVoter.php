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
 * Authorises content-related operations (view, create, edit, publish, archive, rollback).
 *
 * Role mapping (derived from the Meridian role-permission matrix):
 *  - VIEW:     any authenticated user (scope filtering is applied at the query level, not here).
 *  - CREATE:   RECRUITER (job posts), STORE_MANAGER / DISPATCHER / OPERATIONS_ANALYST
 *              (operational notices), ADMINISTRATOR (all).
 *  - EDIT:     same as CREATE, plus scope check is expected at the controller level.
 *  - PUBLISH:  same as EDIT.
 *  - ARCHIVE:  same as EDIT, plus COMPLIANCE_OFFICER.
 *  - ROLLBACK: COMPLIANCE_OFFICER, ADMINISTRATOR. OPERATIONS_ANALYST / RECRUITER only when
 *              explicitly granted (scoped).
 *
 * @extends Voter<string, mixed>
 */
final class ContentVoter extends Voter
{
    private const array SUPPORTED_ATTRIBUTES = [
        Permission::CONTENT_VIEW,
        Permission::CONTENT_CREATE,
        Permission::CONTENT_EDIT,
        Permission::CONTENT_PUBLISH,
        Permission::CONTENT_ARCHIVE,
        Permission::CONTENT_ROLLBACK,
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
            Permission::CONTENT_VIEW => $this->canView($user),
            Permission::CONTENT_CREATE => $this->canCreate($user),
            Permission::CONTENT_EDIT => $this->canEdit($user),
            Permission::CONTENT_PUBLISH => $this->canPublish($user),
            Permission::CONTENT_ARCHIVE => $this->canArchive($user),
            Permission::CONTENT_ROLLBACK => $this->canRollback($user),
            default => false,
        };
    }

    /**
     * Any authenticated user may view content; row-level scope filtering is
     * applied at the repository/query layer rather than in this voter.
     */
    private function canView(User $user): bool
    {
        return true;
    }

    /**
     * RECRUITER creates job posts, STORE_MANAGER/DISPATCHER/OPERATIONS_ANALYST create
     * operational notices, and ADMINISTRATOR can create any content type.
     */
    private function canCreate(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::RECRUITER,
            RoleName::STORE_MANAGER,
            RoleName::DISPATCHER,
            RoleName::OPERATIONS_ANALYST,
            RoleName::ADMINISTRATOR,
        ]);
    }

    private function canEdit(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::RECRUITER,
            RoleName::STORE_MANAGER,
            RoleName::DISPATCHER,
            RoleName::OPERATIONS_ANALYST,
            RoleName::ADMINISTRATOR,
        ]);
    }

    private function canPublish(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::RECRUITER,
            RoleName::STORE_MANAGER,
            RoleName::DISPATCHER,
            RoleName::OPERATIONS_ANALYST,
            RoleName::ADMINISTRATOR,
        ]);
    }

    /**
     * Archive: same as edit, plus COMPLIANCE_OFFICER (review-only / archive authority).
     */
    private function canArchive(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::RECRUITER,
            RoleName::STORE_MANAGER,
            RoleName::DISPATCHER,
            RoleName::OPERATIONS_ANALYST,
            RoleName::COMPLIANCE_OFFICER,
            RoleName::ADMINISTRATOR,
        ]);
    }

    /**
     * Rollback is restricted to COMPLIANCE_OFFICER and ADMINISTRATOR by default.
     * OPERATIONS_ANALYST and RECRUITER may rollback only when scoped and explicitly
     * granted; the voter allows them at the attribute level and expects the
     * controller/service layer to enforce the additional "granted" constraint.
     */
    private function canRollback(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::COMPLIANCE_OFFICER,
            RoleName::ADMINISTRATOR,
            RoleName::OPERATIONS_ANALYST,
            RoleName::RECRUITER,
        ]);
    }
}
