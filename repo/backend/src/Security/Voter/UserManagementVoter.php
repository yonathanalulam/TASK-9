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
 * @extends Voter<string, mixed>
 */
final class UserManagementVoter extends Voter
{
    private const array SUPPORTED_ATTRIBUTES = [
        Permission::USER_VIEW,
        Permission::USER_CREATE,
        Permission::USER_EDIT,
        Permission::USER_DEACTIVATE,
        Permission::ROLE_ASSIGN,
        Permission::ROLE_REVOKE,
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

        // USER_VIEW is also allowed for COMPLIANCE_OFFICER.
        if ($attribute === Permission::USER_VIEW) {
            return $this->rbacService->hasAnyRole($user, [
                RoleName::ADMINISTRATOR,
                RoleName::COMPLIANCE_OFFICER,
            ]);
        }

        // All other operations require ADMINISTRATOR.
        return $this->rbacService->hasAnyRole($user, [RoleName::ADMINISTRATOR]);
    }
}
