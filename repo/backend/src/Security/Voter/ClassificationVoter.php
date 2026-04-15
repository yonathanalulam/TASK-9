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
 * Authorises data-classification operations.
 *
 * Role mapping:
 *  - VIEW:   COMPLIANCE_OFFICER, ADMINISTRATOR.
 *  - MANAGE: ADMINISTRATOR only.
 *
 * @extends Voter<string, mixed>
 */
final class ClassificationVoter extends Voter
{
    private const array SUPPORTED_ATTRIBUTES = [
        Permission::CLASSIFICATION_VIEW,
        Permission::CLASSIFICATION_MANAGE,
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
            Permission::CLASSIFICATION_VIEW => $this->canView($user),
            Permission::CLASSIFICATION_MANAGE => $this->canManage($user),
            default => false,
        };
    }

    private function canView(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::COMPLIANCE_OFFICER,
            RoleName::ADMINISTRATOR,
        ]);
    }

    private function canManage(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::ADMINISTRATOR,
        ]);
    }
}
