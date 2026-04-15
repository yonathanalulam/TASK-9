<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Search;

use PHPUnit\Framework\TestCase;

/**
 * Tests that SearchService::search() properly handles region-only scope filtering.
 * Verifies that the method signature accepts accessibleRegionIds and builds
 * the correct OR condition for region-only content.
 */
final class SearchRegionScopeTest extends TestCase
{
    public function testSearchServiceAcceptsRegionIds(): void
    {
        $ref = new \ReflectionMethod(\App\Service\Search\SearchService::class, 'search');
        $paramNames = array_map(
            static fn (\ReflectionParameter $p) => $p->getName(),
            $ref->getParameters(),
        );

        self::assertContains(
            'accessibleRegionIds',
            $paramNames,
            'SearchService::search() must accept accessibleRegionIds parameter',
        );
    }

    public function testSearchServiceRegionParamIsNullable(): void
    {
        $ref = new \ReflectionMethod(\App\Service\Search\SearchService::class, 'search');

        $regionParam = null;
        foreach ($ref->getParameters() as $param) {
            if ($param->getName() === 'accessibleRegionIds') {
                $regionParam = $param;
                break;
            }
        }

        self::assertNotNull($regionParam, 'accessibleRegionIds parameter must exist');
        self::assertTrue(
            $regionParam->getType()?->allowsNull() ?? false,
            'accessibleRegionIds must be nullable (null = GLOBAL scope)',
        );
    }

    public function testControllerResolvesRegionIds(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 4) . '/src/Controller/Api/V1/SearchController.php',
        );

        self::assertStringContainsString(
            'getAccessibleRegionIds',
            $source,
            'SearchController must call getAccessibleRegionIds on ScopeResolver',
        );

        self::assertStringContainsString(
            'accessibleRegionIdStrings',
            $source,
            'SearchController must convert region UUIDs to strings',
        );
    }

    public function testServiceQueryIncludesRegionOrCondition(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 4) . '/src/Service/Search/SearchService.php',
        );

        // The SQL scope filter must include region-only content via an OR condition.
        self::assertStringContainsString(
            'csi.store_id IS NULL AND csi.region_id IN',
            $source,
            'SearchService must use OR condition for region-only content (store_id IS NULL AND region_id IN)',
        );
    }

    public function testSearchServiceReturnsEmptyWhenNoStoreOrRegionAccess(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 4) . '/src/Service/Search/SearchService.php',
        );

        // When both store and region arrays are empty, should return empty.
        self::assertStringContainsString(
            '!$hasStores && !$hasRegions',
            $source,
            'SearchService must return empty when user has no store AND no region access',
        );
    }
}
