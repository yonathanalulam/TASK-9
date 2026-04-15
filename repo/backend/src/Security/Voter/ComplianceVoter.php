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
 * Authorises compliance-related operations (view, manage, report generation).
 *
 * Only COMPLIANCE_OFFICER and ADMINISTRATOR have access.
 *
 * @extends Voter<string, mixed>
 */
final class ComplianceVoter extends Voter
{
    private const array SUPPORTED_ATTRIBUTES = [
        Permission::COMPLIANCE_VIEW,
        Permission::COMPLIANCE_MANAGE,
        Permission::COMPLIANCE_REPORT_GENERATE,
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
            Permission::COMPLIANCE_VIEW => $this->canView($user),
            Permission::COMPLIANCE_MANAGE => $this->canManage($user),
            Permission::COMPLIANCE_REPORT_GENERATE => $this->canGenerateReport($user),
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
            RoleName::COMPLIANCE_OFFICER,
            RoleName::ADMINISTRATOR,
        ]);
    }

    private function canGenerateReport(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::COMPLIANCE_OFFICER,
            RoleName::ADMINISTRATOR,
        ]);
    }
}
