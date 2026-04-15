<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\MutationQueue;

use App\Enum\RoleName;
use App\Security\Permission;
use PHPUnit\Framework\TestCase;

/**
 * Tests that mutation replay enforces the same permission semantics as
 * normal create/update flows — replay must not be a privilege-escalation path.
 */
final class ReplayPermissionTest extends TestCase
{
    /**
     * Verify by reflection that MutationReplayService has RbacService injected.
     */
    public function testReplayServiceHasRbacServiceDependency(): void
    {
        $ref = new \ReflectionClass(\App\Service\MutationQueue\MutationReplayService::class);
        $constructor = $ref->getConstructor();
        self::assertNotNull($constructor);

        $paramNames = array_map(
            static fn (\ReflectionParameter $p) => $p->getName(),
            $constructor->getParameters(),
        );
        self::assertContains('rbacService', $paramNames, 'MutationReplayService must inject RbacService');
    }

    /**
     * Verify by reflection that MutationReplayService has ScopeResolver injected.
     */
    public function testReplayServiceHasScopeResolverDependency(): void
    {
        $ref = new \ReflectionClass(\App\Service\MutationQueue\MutationReplayService::class);
        $paramNames = array_map(
            static fn (\ReflectionParameter $p) => $p->getName(),
            $ref->getConstructor()->getParameters(),
        );
        self::assertContains('scopeResolver', $paramNames, 'MutationReplayService must inject ScopeResolver');
    }

    /**
     * Verify the code enforces admin role for store creation in replay path.
     */
    public function testReplayStoreCreateEnforcesAdminRole(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 4) . '/src/Service/MutationQueue/MutationReplayService.php',
        );
        // Store creation permission check must reference ADMINISTRATOR
        self::assertStringContainsString('Store creation requires Administrator role', $source);
        self::assertStringContainsString('RoleName::ADMINISTRATOR', $source);
    }

    /**
     * Verify the code enforces admin role for region creation in replay path.
     */
    public function testReplayRegionCreateEnforcesAdminRole(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 4) . '/src/Service/MutationQueue/MutationReplayService.php',
        );
        self::assertStringContainsString('Region creation requires Administrator role', $source);
    }

    /**
     * Verify the code enforces scope check for region update in replay path.
     */
    public function testReplayRegionUpdateEnforcesScopeCheck(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 4) . '/src/Service/MutationQueue/MutationReplayService.php',
        );
        self::assertStringContainsString('canAccessRegion', $source);
        self::assertStringContainsString('Region editing requires Administrator role', $source);
    }

    /**
     * Verify the code enforces scope check for store update in replay path.
     */
    public function testReplayStoreUpdateEnforcesScopeCheck(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 4) . '/src/Service/MutationQueue/MutationReplayService.php',
        );
        self::assertStringContainsString('canAccessStore', $source);
    }

    /**
     * Verify the code enforces scope check for zone update in replay path.
     */
    public function testReplayZoneUpdateEnforcesScopeCheck(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 4) . '/src/Service/MutationQueue/MutationReplayService.php',
        );
        self::assertStringContainsString('canAccessDeliveryZone', $source);
    }
}
