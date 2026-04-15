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
 * Authorises analytics-related operations.
 *
 * Only OPERATIONS_ANALYST, COMPLIANCE_OFFICER, and ADMINISTRATOR may view
 * analytics dashboards and data.
 *
 * @extends Voter<string, mixed>
 */
final class AnalyticsVoter extends Voter
{
    private const array SUPPORTED_ATTRIBUTES = [
        Permission::ANALYTICS_VIEW,
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
            Permission::ANALYTICS_VIEW => $this->canView($user),
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
}
