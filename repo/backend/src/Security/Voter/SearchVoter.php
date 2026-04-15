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
 * Authorises search execution.
 *
 * Per the business specification, all six roles may search content, but with
 * scope-limited results. Store Manager and Dispatcher are limited by scope;
 * the remaining roles have full search access. Scope filtering is applied at
 * query time by the SearchService, not by this voter.
 *
 * @extends Voter<string, mixed>
 */
final class SearchVoter extends Voter
{
    private const array SUPPORTED_ATTRIBUTES = [
        Permission::SEARCH_EXECUTE,
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

        // All six canonical roles can execute search.
        return $this->rbacService->hasAnyRole($user, [
            RoleName::STORE_MANAGER,
            RoleName::DISPATCHER,
            RoleName::OPERATIONS_ANALYST,
            RoleName::RECRUITER,
            RoleName::COMPLIANCE_OFFICER,
            RoleName::ADMINISTRATOR,
        ]);
    }
}
