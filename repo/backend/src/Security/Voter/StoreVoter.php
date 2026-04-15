<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Store;
use App\Entity\User;
use App\Enum\RoleName;
use App\Security\Permission;
use App\Service\Authorization\RbacService;
use App\Service\Authorization\ScopeResolver;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Store|null>
 */
final class StoreVoter extends Voter
{
    private const array SUPPORTED_ATTRIBUTES = [
        Permission::STORE_VIEW,
        Permission::STORE_EDIT,
        Permission::STORE_CREATE,
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

        // Subjectless calls are valid for CREATE, VIEW (list), and EDIT (feature access).
        // Subject-aware calls are valid for VIEW/EDIT on a concrete Store.
        if ($subject === null) {
            return true; // All store permissions support subjectless role checks
        }

        return $subject instanceof Store;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            Permission::STORE_VIEW => $subject instanceof Store
                ? $this->canViewSubject($user, $subject)
                : $this->canViewList($user),
            Permission::STORE_EDIT => $subject instanceof Store
                ? $this->canEdit($user, $subject)
                : $this->canEditList($user),
            Permission::STORE_CREATE => $this->canCreate($user),
            default => false,
        };
    }

    /** Subjectless: does the user have any role that permits listing stores? */
    private function canViewList(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::STORE_MANAGER,
            RoleName::DISPATCHER,
            RoleName::ADMINISTRATOR,
            RoleName::COMPLIANCE_OFFICER,
        ]);
    }

    /** Subject-aware: does the user have role + scope access to this store? */
    private function canViewSubject(User $user, Store $store): bool
    {
        return $this->canViewList($user)
            && $this->scopeResolver->canAccessStore($user, $store);
    }

    /** Subjectless: does the user have any role that permits editing stores? */
    private function canEditList(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::STORE_MANAGER,
            RoleName::ADMINISTRATOR,
        ]);
    }

    private function canEdit(User $user, Store $store): bool
    {
        return $this->canEditList($user)
            && $this->scopeResolver->canAccessStore($user, $store);
    }

    private function canCreate(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [RoleName::ADMINISTRATOR]);
    }
}
