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
 * Authorises mutation queue operations.
 *
 * MUTATION_REPLAY: any role that mutates data can replay queued mutations.
 * MUTATION_VIEW_ADMIN: only Administrator can view the admin mutation log.
 *
 * @extends Voter<string, mixed>
 */
final class MutationQueueVoter extends Voter
{
    private const array SUPPORTED_ATTRIBUTES = [
        Permission::MUTATION_REPLAY,
        Permission::MUTATION_VIEW_ADMIN,
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
            Permission::MUTATION_REPLAY => $this->canReplay($user),
            Permission::MUTATION_VIEW_ADMIN => $this->canViewAdmin($user),
            default => false,
        };
    }

    private function canReplay(User $user): bool
    {
        // Any role that performs mutations can replay their queued changes.
        return $this->rbacService->hasAnyRole($user, [
            RoleName::STORE_MANAGER,
            RoleName::DISPATCHER,
            RoleName::OPERATIONS_ANALYST,
            RoleName::RECRUITER,
            RoleName::COMPLIANCE_OFFICER,
            RoleName::ADMINISTRATOR,
        ]);
    }

    private function canViewAdmin(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::ADMINISTRATOR,
        ]);
    }
}
