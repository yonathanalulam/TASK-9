<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\User;
use App\Enum\RoleName;
use App\Security\Permission;
use App\Security\Voter\MutationQueueVoter;
use App\Service\Authorization\RbacService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Unit tests for MutationQueueVoter.
 *
 * MUTATION_REPLAY: any authenticated role is granted.
 * MUTATION_VIEW_ADMIN: only ADMINISTRATOR is granted.
 */
final class MutationQueueVoterTest extends TestCase
{
    private RbacService&MockObject $rbacService;
    private MutationQueueVoter $voter;

    protected function setUp(): void
    {
        $this->rbacService = $this->createMock(RbacService::class);
        $this->voter = new MutationQueueVoter($this->rbacService);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createTokenForUser(): TokenInterface
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    /**
     * Configure the mock so that hasAnyRole returns true only when the
     * requested role list contains $grantedRole.
     */
    private function grantOnlyRole(RoleName $grantedRole): void
    {
        $this->rbacService->method('hasAnyRole')
            ->willReturnCallback(static function (User $user, array $roles) use ($grantedRole): bool {
                return \in_array($grantedRole, $roles, true);
            });
    }

    // ------------------------------------------------------------------
    // MUTATION_REPLAY -- any authenticated role can replay.
    // ------------------------------------------------------------------

    public function testAnyAuthenticatedRoleCanReplay(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(true);
        $token = $this->createTokenForUser();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, null, [Permission::MUTATION_REPLAY]),
        );
    }

    public function testReplayDeniedWhenNoRole(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(false);
        $token = $this->createTokenForUser();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, null, [Permission::MUTATION_REPLAY]),
        );
    }

    // ------------------------------------------------------------------
    // MUTATION_VIEW_ADMIN -- ADMINISTRATOR only.
    // ------------------------------------------------------------------

    public function testAdministratorCanViewAdmin(): void
    {
        $this->grantOnlyRole(RoleName::ADMINISTRATOR);
        $token = $this->createTokenForUser();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, null, [Permission::MUTATION_VIEW_ADMIN]),
        );
    }

    public function testStoreManagerCannotViewAdmin(): void
    {
        $this->grantOnlyRole(RoleName::STORE_MANAGER);
        $token = $this->createTokenForUser();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, null, [Permission::MUTATION_VIEW_ADMIN]),
        );
    }

    public function testOperationsAnalystCannotViewAdmin(): void
    {
        $this->grantOnlyRole(RoleName::OPERATIONS_ANALYST);
        $token = $this->createTokenForUser();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, null, [Permission::MUTATION_VIEW_ADMIN]),
        );
    }

    // ------------------------------------------------------------------
    // Edge cases
    // ------------------------------------------------------------------

    public function testNonUserTokenIsDeniedForReplay(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, null, [Permission::MUTATION_REPLAY]),
        );
    }

    public function testNonUserTokenIsDeniedForViewAdmin(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, null, [Permission::MUTATION_VIEW_ADMIN]),
        );
    }

    public function testUnsupportedAttributeAbstains(): void
    {
        $token = $this->createTokenForUser();

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($token, null, ['SOME_OTHER_PERMISSION']),
        );
    }
}
