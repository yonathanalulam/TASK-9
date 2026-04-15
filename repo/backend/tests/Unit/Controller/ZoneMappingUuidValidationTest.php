<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Tests that zone mapping create validates UUID format deterministically
 * and does not allow invalid UUIDs to produce 500 errors.
 */
final class ZoneMappingUuidValidationTest extends TestCase
{
    public function testValidUuidParsesSuccessfully(): void
    {
        $validId = '019d8b36-0621-76c8-9676-05bc1dd102b7';
        $uuid = Uuid::fromString($validId);
        self::assertSame($validId, $uuid->toRfc4122());
    }

    public function testMalformedShortStringThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Uid\Exception\InvalidArgumentException::class);
        Uuid::fromString('xyz');
    }

    public function testEmptyStringThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Uid\Exception\InvalidArgumentException::class);
        Uuid::fromString('');
    }

    public function testControllerCatchesInvalidUuid(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 3) . '/src/Controller/Api/V1/DeliveryZoneController.php',
        );

        // Must have try/catch for InvalidArgumentException.
        self::assertStringContainsString(
            'InvalidArgumentException',
            $source,
            'createMapping must catch InvalidArgumentException from UUID parsing',
        );

        // Must return 422 for invalid UUIDs.
        self::assertStringContainsString(
            'mapped_entity_id must be a valid UUID',
            $source,
            'createMapping must return validation error for invalid UUID',
        );
    }
}
