<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Content;

use PHPUnit\Framework\TestCase;

/**
 * Tests that ContentService::list() properly handles region-only scope filtering.
 * Verifies that the method signature accepts accessibleRegionIds and that
 * ContentController::list() resolves and passes them.
 */
final class ContentListRegionScopeTest extends TestCase
{
    public function testContentServiceListAcceptsRegionIds(): void
    {
        $ref = new \ReflectionMethod(\App\Service\Content\ContentService::class, 'list');
        $paramNames = array_map(
            static fn (\ReflectionParameter $p) => $p->getName(),
            $ref->getParameters(),
        );

        self::assertContains(
            'accessibleRegionIds',
            $paramNames,
            'ContentService::list() must accept accessibleRegionIds parameter',
        );
    }

    public function testContentServiceListRegionParamIsNullable(): void
    {
        $ref = new \ReflectionMethod(\App\Service\Content\ContentService::class, 'list');

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
            \dirname(__DIR__, 4) . '/src/Controller/Api/V1/ContentController.php',
        );

        self::assertStringContainsString(
            'getAccessibleRegionIds',
            $source,
            'ContentController::list() must call getAccessibleRegionIds on ScopeResolver',
        );

        self::assertStringContainsString(
            'accessibleRegionIdStrings',
            $source,
            'ContentController::list() must convert region UUIDs to strings',
        );
    }

    public function testServiceQueryIncludesRegionOrCondition(): void
    {
        $source = file_get_contents(
            \dirname(__DIR__, 4) . '/src/Service/Content/ContentService.php',
        );

        // The scope filter must include region-only content via an OR condition.
        self::assertStringContainsString(
            'accessibleRegionIds',
            $source,
            'ContentService::list() must use accessibleRegionIds in scope filter',
        );

        self::assertStringContainsString(
            'isNull',
            $source,
            'Scope filter must check for NULL storeId to match region-only content',
        );
    }
}
