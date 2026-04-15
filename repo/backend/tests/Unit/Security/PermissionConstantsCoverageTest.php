<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\Permission;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that Permission contains all expected constants and that no
 * voter class still defines its own public permission string constants.
 */
final class PermissionConstantsCoverageTest extends TestCase
{
    /**
     * Every permission string used across the platform MUST appear as a
     * public string constant on the Permission class.
     */
    public function testAllExpectedPermissionsAreCentralised(): void
    {
        $expected = [
            // Content
            'CONTENT_VIEW', 'CONTENT_CREATE', 'CONTENT_EDIT',
            'CONTENT_PUBLISH', 'CONTENT_ARCHIVE', 'CONTENT_ROLLBACK',
            // Search
            'SEARCH_EXECUTE',
            // Import / Dedup
            'IMPORT_CREATE', 'IMPORT_VIEW',
            'DEDUP_REVIEW', 'DEDUP_MERGE', 'DEDUP_UNMERGE',
            // Export
            'EXPORT_REQUEST', 'EXPORT_AUTHORIZE', 'EXPORT_VIEW', 'EXPORT_DOWNLOAD',
            // Compliance
            'COMPLIANCE_VIEW', 'COMPLIANCE_MANAGE', 'COMPLIANCE_REPORT_GENERATE',
            // Classification
            'CLASSIFICATION_VIEW', 'CLASSIFICATION_MANAGE',
            // Analytics
            'ANALYTICS_VIEW',
            // Warehouse
            'WAREHOUSE_VIEW', 'WAREHOUSE_TRIGGER',
            // Mutation Queue
            'MUTATION_REPLAY', 'MUTATION_VIEW_ADMIN',
            // Scraping
            'SCRAPING_VIEW', 'SCRAPING_MANAGE', 'SCRAPING_TRIGGER',
            // Store
            'STORE_VIEW', 'STORE_EDIT', 'STORE_CREATE',
            // Delivery Zone
            'ZONE_VIEW', 'ZONE_EDIT', 'ZONE_CREATE',
            // Region
            'REGION_VIEW', 'REGION_EDIT', 'REGION_CREATE', 'REGION_CLOSE',
            // User Management
            'USER_VIEW', 'USER_CREATE', 'USER_EDIT', 'USER_DEACTIVATE',
            'ROLE_ASSIGN', 'ROLE_REVOKE',
        ];

        $reflection = new \ReflectionClass(Permission::class);
        $constants = $reflection->getConstants();

        foreach ($expected as $name) {
            self::assertArrayHasKey($name, $constants, "Permission::{$name} must be defined.");
            self::assertSame($name, $constants[$name], "Permission::{$name} value must equal its name.");
        }
    }

    /**
     * The constant value MUST match the constant name — this prevents
     * copy-paste drift (e.g. STORE_VIEW = 'STORE_EDIT').
     */
    public function testConstantValuesMatchNames(): void
    {
        $reflection = new \ReflectionClass(Permission::class);

        foreach ($reflection->getConstants() as $name => $value) {
            self::assertSame($name, $value, "Permission::{$name} has mismatched value '{$value}'.");
        }
    }

    /**
     * Voter classes must NOT define their own public permission string
     * constants — all permission strings live in Permission.php.
     */
    public function testNoVoterDefinesPublicPermissionConstants(): void
    {
        $voterDir = __DIR__ . '/../../src/Security/Voter';

        // Fallback: compute from project root if the relative path does not exist.
        if (!is_dir($voterDir)) {
            $voterDir = dirname(__DIR__, 2) . '/src/Security/Voter';
        }

        if (!is_dir($voterDir)) {
            self::fail(
                'Voter directory not found at expected path: ' . $voterDir
                . ' — the project structure has changed or the test path computation is wrong.',
            );
        }

        foreach (glob($voterDir . '/*Voter.php') as $file) {
            $content = file_get_contents($file);
            $basename = basename($file);

            // Match public const string declarations with UPPER_CASE values.
            self::assertDoesNotMatchRegularExpression(
                '/public\s+const\s+string\s+\w+\s*=\s*\'[A-Z_]+\'\s*;/',
                $content,
                "{$basename} still defines its own public permission constant — use Permission:: instead.",
            );
        }
    }
}
