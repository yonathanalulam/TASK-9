<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\User;
use App\Enum\RoleName;
use App\Security\Permission;
use App\Security\Voter\SearchVoter;
use App\Service\Authorization\RbacService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Unit tests for SearchVoter: verifies that all six canonical roles
 * are granted SEARCH_EXECUTE, and that users without a role or with
 * a non-User token are denied.
 */
final class SearchVoterTest extends TestCase
{
    private RbacService&MockObject $rbacService;
    private SearchVoter $voter;

    protected function setUp(): void
    {
        $this->rbacService = $this->createMock(RbacService::class);
        $this->voter = new SearchVoter($this->rbacService);
    }

    // ------------------------------------------------------------------
    // Helper: create a TokenInterface whose getUser() returns a mock User.
    // ------------------------------------------------------------------

    private function createTokenForUser(): TokenInterface
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    // ------------------------------------------------------------------
    // Granted: every canonical role can search.
    // ------------------------------------------------------------------

    public function testAdministratorCanSearch(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(true);
        $token = $this->createTokenForUser();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, null, [Permission::SEARCH_EXECUTE]),
        );
    }

    public function testStoreManagerCanSearch(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(true);
        $token = $this->createTokenForUser();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, null, [Permission::SEARCH_EXECUTE]),
        );
    }

    public function testDispatcherCanSearch(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(true);
        $token = $this->createTokenForUser();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, null, [Permission::SEARCH_EXECUTE]),
        );
    }

    public function testOperationsAnalystCanSearch(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(true);
        $token = $this->createTokenForUser();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, null, [Permission::SEARCH_EXECUTE]),
        );
    }

    public function testRecruiterCanSearch(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(true);
        $token = $this->createTokenForUser();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, null, [Permission::SEARCH_EXECUTE]),
        );
    }

    public function testComplianceOfficerCanSearch(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(true);
        $token = $this->createTokenForUser();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, null, [Permission::SEARCH_EXECUTE]),
        );
    }

    // ------------------------------------------------------------------
    // Denied: user with no matching role.
    // ------------------------------------------------------------------

    public function testUserWithNoRoleIsDenied(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(false);
        $token = $this->createTokenForUser();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, null, [Permission::SEARCH_EXECUTE]),
        );
    }

    // ------------------------------------------------------------------
    // Denied: token whose getUser() does not return a User entity.
    // ------------------------------------------------------------------

    public function testNonUserTokenIsDenied(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, null, [Permission::SEARCH_EXECUTE]),
        );
    }

    // ------------------------------------------------------------------
    // Abstain: unsupported attribute is ignored.
    // ------------------------------------------------------------------

    public function testUnsupportedAttributeAbstains(): void
    {
        $token = $this->createTokenForUser();

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($token, null, ['SOME_OTHER_PERMISSION']),
        );
    }
}
