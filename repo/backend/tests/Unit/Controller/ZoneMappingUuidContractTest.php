<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

/**
 * Verifies zone mapping endpoints return mapped_entity_id in a consistent
 * canonical UUID format (RFC4122), not hex.
 */
final class ZoneMappingUuidContractTest extends TestCase
{
    /**
     * The listMappings() method must use Uuid::fromBinary()->toRfc4122(), not bin2hex().
     */
    public function testListMappingsUsesRfc4122NotBinHex(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 3) . '/src/Controller/Api/V1/DeliveryZoneController.php',
        );

        // Must NOT use bin2hex for mapped_entity_id.
        self::assertStringNotContainsString(
            "bin2hex(\$m->getMappedEntityId())",
            $source,
            'listMappings must not use bin2hex for mapped_entity_id',
        );

        // Must use Uuid::fromBinary()->toRfc4122().
        self::assertStringContainsString(
            'Uuid::fromBinary($m->getMappedEntityId())->toRfc4122()',
            $source,
            'listMappings must use Uuid::fromBinary()->toRfc4122() for mapped_entity_id',
        );
    }

    /**
     * Verify that the mapping create and list endpoints use subject-aware
     * permission checks (not subjectless zone voter calls).
     */
    public function testMappingEndpointsUseSubjectAwareAuth(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 3) . '/src/Controller/Api/V1/DeliveryZoneController.php',
        );

        // createMapping must pass zone subject to ZONE_EDIT.
        self::assertStringContainsString(
            'denyAccessUnlessGranted(Permission::ZONE_EDIT, $zone)',
            $source,
            'createMapping must perform subject-aware ZONE_EDIT check',
        );

        // listMappings must pass zone subject to ZONE_VIEW.
        self::assertStringContainsString(
            'denyAccessUnlessGranted(Permission::ZONE_VIEW, $zone)',
            $source,
            'listMappings must perform subject-aware ZONE_VIEW check',
        );
    }
}
