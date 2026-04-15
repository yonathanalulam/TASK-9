<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\DeliveryZone;
use App\Entity\Store;
use App\Entity\User;
use App\Enum\RoleName;
use App\Security\Permission;
use App\Service\Authorization\RbacService;
use App\Service\Authorization\ScopeResolver;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, DeliveryZone|Store|null>
 */
final class DeliveryZoneVoter extends Voter
{
    private const array SUPPORTED_ATTRIBUTES = [
        Permission::ZONE_VIEW,
        Permission::ZONE_EDIT,
        Permission::ZONE_CREATE,
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

        // Subjectless calls are valid for VIEW (list access), EDIT (list-level), CREATE.
        if ($subject === null) {
            return true;
        }

        return $subject instanceof DeliveryZone || $subject instanceof Store;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            Permission::ZONE_VIEW => $subject instanceof DeliveryZone
                ? $this->canView($user, $subject)
                : $this->canViewList($user),
            Permission::ZONE_EDIT => $subject instanceof DeliveryZone
                ? $this->canEdit($user, $subject)
                : $this->canEditList($user),
            Permission::ZONE_CREATE => $this->canCreate($user, $subject),
            default => false,
        };
    }

    /** Subjectless: does the user have any role that permits listing zones? */
    private function canViewList(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::STORE_MANAGER,
            RoleName::DISPATCHER,
            RoleName::ADMINISTRATOR,
            RoleName::COMPLIANCE_OFFICER,
        ]);
    }

    /** Subjectless: does the user have any role that permits zone editing features? */
    private function canEditList(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::STORE_MANAGER,
            RoleName::DISPATCHER,
            RoleName::ADMINISTRATOR,
        ]);
    }

    private function canView(User $user, DeliveryZone $zone): bool
    {
        $allowedRoles = [
            RoleName::STORE_MANAGER,
            RoleName::DISPATCHER,
            RoleName::ADMINISTRATOR,
            RoleName::COMPLIANCE_OFFICER,
        ];

        return $this->rbacService->hasAnyRole($user, $allowedRoles)
            && $this->scopeResolver->canAccessDeliveryZone($user, $zone);
    }

    private function canEdit(User $user, DeliveryZone $zone): bool
    {
        $allowedRoles = [
            RoleName::STORE_MANAGER,
            RoleName::DISPATCHER,
            RoleName::ADMINISTRATOR,
        ];

        return $this->rbacService->hasAnyRole($user, $allowedRoles)
            && $this->scopeResolver->canAccessDeliveryZone($user, $zone);
    }

    private function canCreate(User $user, Store|DeliveryZone|null $subject): bool
    {
        $allowedRoles = [
            RoleName::STORE_MANAGER,
            RoleName::DISPATCHER,
            RoleName::ADMINISTRATOR,
        ];

        if (!$this->rbacService->hasAnyRole($user, $allowedRoles)) {
            return false;
        }

        if ($subject instanceof Store) {
            return $this->scopeResolver->canAccessStore($user, $subject);
        }

        // No store context provided -- role check alone is sufficient.
        return true;
    }
}
