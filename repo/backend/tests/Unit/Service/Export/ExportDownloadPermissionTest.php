<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Export;

use App\Security\Permission;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that EXPORT_DOWNLOAD is a dedicated permission constant,
 * distinct from EXPORT_VIEW, so that download access can be gated
 * independently.
 */
final class ExportDownloadPermissionTest extends TestCase
{
    public function testExportDownloadPermissionIsDedicatedConstant(): void
    {
        self::assertSame('EXPORT_DOWNLOAD', Permission::EXPORT_DOWNLOAD);
        self::assertNotSame(Permission::EXPORT_VIEW, Permission::EXPORT_DOWNLOAD);
    }
}
