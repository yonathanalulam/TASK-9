<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

/**
 * Verifies the content show scope check compares UUIDs using canonical
 * string representation on both sides, not mixing Uuid objects with strings.
 */
final class ContentScopeUuidTest extends TestCase
{
    /**
     * The scope check in ContentController::show() must convert the content
     * item's storeId (which is a Uuid object) to RFC4122 string before comparing
     * with the accessible store IDs (also converted to RFC4122 strings).
     */
    public function testContentShowScopeCheckUsesCanonicalUuidComparison(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 3) . '/src/Controller/Api/V1/ContentController.php',
        );

        // Must call toRfc4122() on the item's store ID, not compare object directly.
        self::assertStringContainsString(
            '$item->getStoreId()->toRfc4122()',
            $source,
            'Content show scope check must normalize item store ID to RFC4122 string',
        );

        // Must NOT compare Uuid object directly with string (the old bug).
        self::assertStringNotContainsString(
            '$uuid->toRfc4122() === $itemStoreId)',
            $source,
            'Must not compare RFC4122 string against raw Uuid object',
        );
    }

    /**
     * UUID equality: verify that converting both sides to RFC4122 produces correct equality.
     */
    public function testUuidRfc4122EqualityIsCanonical(): void
    {
        $uuid = \Symfony\Component\Uid\Uuid::fromString('019d8b36-0621-76c8-9676-05bc1dd102b7');
        $sameUuidStr = '019d8b36-0621-76c8-9676-05bc1dd102b7';

        // Both sides as RFC4122 strings must be equal.
        self::assertSame($uuid->toRfc4122(), $sameUuidStr);
    }
}
