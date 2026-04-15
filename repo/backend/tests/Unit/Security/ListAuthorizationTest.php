<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\Permission;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that the Permission constants required for list (index) operations
 * exist and carry the expected values.
 */
final class ListAuthorizationTest extends TestCase
{
    public function testRegionViewPermissionExists(): void
    {
        self::assertSame('REGION_VIEW', Permission::REGION_VIEW);
    }

    public function testZoneViewPermissionExists(): void
    {
        self::assertSame('ZONE_VIEW', Permission::ZONE_VIEW);
    }

    public function testStoreViewPermissionExists(): void
    {
        self::assertSame('STORE_VIEW', Permission::STORE_VIEW);
    }

    public function testContentViewPermissionExists(): void
    {
        self::assertSame('CONTENT_VIEW', Permission::CONTENT_VIEW);
    }
}
