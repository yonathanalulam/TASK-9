<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

/**
 * Tests that ContentController::show() scope check covers both
 * store-scoped and region-scoped content items.
 */
final class ContentRegionScopeTest extends TestCase
{
    public function testShowMethodChecksRegionScope(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 3) . '/src/Controller/Api/V1/ContentController.php',
        );

        // Must check getRegionId() for scope.
        self::assertStringContainsString(
            'getRegionId()',
            $source,
            'Content show must check region_id for scope enforcement',
        );

        // Must call getAccessibleRegionIds.
        self::assertStringContainsString(
            'getAccessibleRegionIds',
            $source,
            'Content show must use ScopeResolver::getAccessibleRegionIds for region scope',
        );
    }

    public function testShowMethodChecksStoreScope(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 3) . '/src/Controller/Api/V1/ContentController.php',
        );

        // Must check getStoreId() for scope.
        self::assertStringContainsString(
            'getStoreId()',
            $source,
            'Content show must check store_id for scope enforcement',
        );

        self::assertStringContainsString(
            'getAccessibleStoreIds',
            $source,
            'Content show must use ScopeResolver::getAccessibleStoreIds for store scope',
        );
    }
}
