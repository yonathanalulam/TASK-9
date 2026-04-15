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
 * Authorises export-related operations (request, authorise, view, download).
 *
 * Role mapping:
 *  - REQUEST:   COMPLIANCE_OFFICER, ADMINISTRATOR. OPERATIONS_ANALYST and RECRUITER
 *               only when explicitly granted.
 *  - AUTHORIZE: COMPLIANCE_OFFICER, ADMINISTRATOR.
 *  - VIEW:      COMPLIANCE_OFFICER, ADMINISTRATOR.
 *  - DOWNLOAD:  COMPLIANCE_OFFICER, ADMINISTRATOR.
 *
 * @extends Voter<string, mixed>
 */
final class ExportVoter extends Voter
{
    private const array SUPPORTED_ATTRIBUTES = [
        Permission::EXPORT_REQUEST,
        Permission::EXPORT_AUTHORIZE,
        Permission::EXPORT_VIEW,
        Permission::EXPORT_DOWNLOAD,
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
            Permission::EXPORT_REQUEST => $this->canRequest($user),
            Permission::EXPORT_AUTHORIZE => $this->canAuthorize($user),
            Permission::EXPORT_VIEW => $this->canView($user),
            Permission::EXPORT_DOWNLOAD => $this->canDownload($user),
            default => false,
        };
    }

    /**
     * COMPLIANCE_OFFICER and ADMINISTRATOR may always request exports.
     * OPERATIONS_ANALYST and RECRUITER may request only when explicitly granted;
     * the voter permits entry and the service layer enforces the grant check.
     */
    private function canRequest(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::COMPLIANCE_OFFICER,
            RoleName::ADMINISTRATOR,
            RoleName::OPERATIONS_ANALYST,
            RoleName::RECRUITER,
        ]);
    }

    private function canAuthorize(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::COMPLIANCE_OFFICER,
            RoleName::ADMINISTRATOR,
        ]);
    }

    private function canView(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::COMPLIANCE_OFFICER,
            RoleName::ADMINISTRATOR,
        ]);
    }

    private function canDownload(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::COMPLIANCE_OFFICER,
            RoleName::ADMINISTRATOR,
        ]);
    }
}
