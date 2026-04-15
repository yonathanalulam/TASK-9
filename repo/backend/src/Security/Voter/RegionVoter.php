<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\MdmRegion;
use App\Entity\User;
use App\Enum\RoleName;
use App\Security\Permission;
use App\Service\Authorization\RbacService;
use App\Service\Authorization\ScopeResolver;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, MdmRegion|null>
 */
final class RegionVoter extends Voter
{
    private const array SUPPORTED_ATTRIBUTES = [
        Permission::REGION_VIEW,
        Permission::REGION_EDIT,
        Permission::REGION_CREATE,
        Permission::REGION_CLOSE,
    ];

    public function __construct(
        private readonly RbacService $rbacService,
        private readonly ScopeResolver $scopeResolver,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!\in_array($attribute, self::SUPPORTED_ATTRIBUTES, true)) {
            return false;
        }

        // Subjectless calls are valid for VIEW (list), CREATE, EDIT, CLOSE.
        if ($subject === null) {
            return true;
        }

        return $subject instanceof MdmRegion;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            Permission::REGION_VIEW => $subject instanceof MdmRegion
                ? $this->canViewSubject($user, $subject)
                : $this->canViewList($user),
            Permission::REGION_EDIT => $this->canEdit($user),
            Permission::REGION_CREATE => $this->canCreate($user),
            Permission::REGION_CLOSE => $this->canClose($user),
            default => false,
        };
    }

    /** Subjectless: does the user have any role that permits listing regions? */
    private function canViewList(User $user): bool
    {
        // All six canonical roles can view regions (they need region info for scope context).
        return $this->rbacService->hasAnyRole($user, [
            RoleName::STORE_MANAGER,
            RoleName::DISPATCHER,
            RoleName::OPERATIONS_ANALYST,
            RoleName::RECRUITER,
            RoleName::COMPLIANCE_OFFICER,
            RoleName::ADMINISTRATOR,
        ]);
    }

    /** Subject-aware: does the user have role + scope access to this region? */
    private function canViewSubject(User $user, MdmRegion $region): bool
    {
        if ($this->rbacService->hasAnyRole($user, [RoleName::ADMINISTRATOR, RoleName::COMPLIANCE_OFFICER])) {
            return true;
        }

        return $this->scopeResolver->canAccessRegion($user, $region);
    }

    private function canEdit(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [RoleName::ADMINISTRATOR]);
    }

    private function canCreate(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [RoleName::ADMINISTRATOR]);
    }

    private function canClose(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [RoleName::ADMINISTRATOR]);
    }
}
