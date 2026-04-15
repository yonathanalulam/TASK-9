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
 * Authorises warehouse / data-load operations.
 *
 * Role mapping:
 *  - VIEW:    OPERATIONS_ANALYST, COMPLIANCE_OFFICER, ADMINISTRATOR.
 *  - TRIGGER: ADMINISTRATOR only.
 *
 * @extends Voter<string, mixed>
 */
final class WarehouseVoter extends Voter
{
    private const array SUPPORTED_ATTRIBUTES = [
        Permission::WAREHOUSE_VIEW,
        Permission::WAREHOUSE_TRIGGER,
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
            Permission::WAREHOUSE_VIEW => $this->canView($user),
            Permission::WAREHOUSE_TRIGGER => $this->canTrigger($user),
            default => false,
        };
    }

    private function canView(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::OPERATIONS_ANALYST,
            RoleName::COMPLIANCE_OFFICER,
            RoleName::ADMINISTRATOR,
        ]);
    }

    private function canTrigger(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::ADMINISTRATOR,
        ]);
    }
}
