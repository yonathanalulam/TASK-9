<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\User;
use App\Security\Permission;
use App\Security\Voter\DeliveryZoneVoter;
use App\Security\Voter\RegionVoter;
use App\Security\Voter\StoreVoter;
use App\Service\Authorization\RbacService;
use App\Service\Authorization\ScopeResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Proves that list-route voter calls (subjectless) are handled correctly
 * and do not cause the voter to abstain when the user has the right role.
 */
final class SubjectlessVoterTest extends TestCase
{
    private RbacService $rbacService;
    private ScopeResolver $scopeResolver;

    protected function setUp(): void
    {
        $this->rbacService = $this->createMock(RbacService::class);
        $this->scopeResolver = $this->createMock(ScopeResolver::class);
    }

    private function tokenForUser(): TokenInterface
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        return $token;
    }

    // ---- StoreVoter ----

    public function testStoreViewSubjectlessGrantedForAuthorizedRole(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(true);
        $voter = new StoreVoter($this->rbacService, $this->scopeResolver);

        // Subjectless call — must NOT abstain
        $result = $voter->vote($this->tokenForUser(), null, [Permission::STORE_VIEW]);
        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testStoreViewSubjectlessDeniedForUnauthorizedRole(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(false);
        $voter = new StoreVoter($this->rbacService, $this->scopeResolver);

        $result = $voter->vote($this->tokenForUser(), null, [Permission::STORE_VIEW]);
        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ---- RegionVoter ----

    public function testRegionViewSubjectlessGrantedForAuthorizedRole(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(true);
        $voter = new RegionVoter($this->rbacService, $this->scopeResolver);

        $result = $voter->vote($this->tokenForUser(), null, [Permission::REGION_VIEW]);
        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testRegionViewSubjectlessDeniedForUnauthorizedRole(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(false);
        $voter = new RegionVoter($this->rbacService, $this->scopeResolver);

        $result = $voter->vote($this->tokenForUser(), null, [Permission::REGION_VIEW]);
        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ---- DeliveryZoneVoter ----

    public function testZoneViewSubjectlessGrantedForAuthorizedRole(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(true);
        $voter = new DeliveryZoneVoter($this->rbacService, $this->scopeResolver);

        $result = $voter->vote($this->tokenForUser(), null, [Permission::ZONE_VIEW]);
        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testZoneViewSubjectlessDeniedForUnauthorizedRole(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(false);
        $voter = new DeliveryZoneVoter($this->rbacService, $this->scopeResolver);

        $result = $voter->vote($this->tokenForUser(), null, [Permission::ZONE_VIEW]);
        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testZoneEditSubjectlessGrantedForAuthorizedRole(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(true);
        $voter = new DeliveryZoneVoter($this->rbacService, $this->scopeResolver);

        $result = $voter->vote($this->tokenForUser(), null, [Permission::ZONE_EDIT]);
        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testZoneEditSubjectlessDeniedForUnauthorizedRole(): void
    {
        $this->rbacService->method('hasAnyRole')->willReturn(false);
        $voter = new DeliveryZoneVoter($this->rbacService, $this->scopeResolver);

        $result = $voter->vote($this->tokenForUser(), null, [Permission::ZONE_EDIT]);
        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }
}
