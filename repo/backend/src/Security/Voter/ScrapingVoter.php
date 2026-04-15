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
 * Authorises scraping infrastructure operations (source definitions, scrape runs, health).
 *
 * Scraping is an infrastructure-level concern restricted to ADMINISTRATOR only.
 *
 * @extends Voter<string, mixed>
 */
final class ScrapingVoter extends Voter
{
    private const array SUPPORTED_ATTRIBUTES = [
        Permission::SCRAPING_VIEW,
        Permission::SCRAPING_MANAGE,
        Permission::SCRAPING_TRIGGER,
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
            Permission::SCRAPING_VIEW => $this->canView($user),
            Permission::SCRAPING_MANAGE => $this->canManage($user),
            Permission::SCRAPING_TRIGGER => $this->canTrigger($user),
            default => false,
        };
    }

    private function canView(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
            RoleName::ADMINISTRATOR,
        ]);
    }

    private function canManage(User $user): bool
    {
        return $this->rbacService->hasAnyRole($user, [
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
