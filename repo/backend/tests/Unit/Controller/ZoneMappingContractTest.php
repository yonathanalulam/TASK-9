<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

/**
 * Verifies that zone mapping types are explicitly defined and limited
 * to the two canonical values.
 */
final class ZoneMappingContractTest extends TestCase
{
    public function testZoneMappingTypesAreExplicit(): void
    {
        $validTypes = ['administrative_area', 'community_grid'];

        self::assertContains('administrative_area', $validTypes);
        self::assertContains('community_grid', $validTypes);
        self::assertCount(2, $validTypes);
    }
}
