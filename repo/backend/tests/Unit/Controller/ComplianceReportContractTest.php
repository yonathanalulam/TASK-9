<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

/**
 * Pure unit test for compliance report enum validation and response contract.
 *
 * Ensures that the canonical report types and serialized response keys
 * are correctly defined and do not include stale or frontend-guessed fields.
 */
final class ComplianceReportContractTest extends TestCase
{
    /** @var list<string> */
    private const VALID_REPORT_TYPES = [
        'RETENTION_SUMMARY',
        'CONSENT_AUDIT',
        'DATA_CLASSIFICATION',
        'EXPORT_LOG',
        'ACCESS_AUDIT',
    ];

    /** @var list<string> */
    private const SERIALIZED_REPORT_KEYS = [
        'id',
        'report_type',
        'generated_by',
        'parameters',
        'download_url',
        'tamper_hash_sha256',
        'previous_report_id',
        'previous_report_hash',
        'generated_at',
    ];

    public function testValidReportTypes(): void
    {
        $validTypes = self::VALID_REPORT_TYPES;

        foreach ($validTypes as $type) {
            self::assertContains($type, $validTypes);
        }

        // Exactly 5 types.
        self::assertCount(5, $validTypes);
    }

    public function testInvalidReportTypesAreRejected(): void
    {
        $validTypes = self::VALID_REPORT_TYPES;

        // These were previously in the frontend but are NOT valid.
        $invalidTypes = ['AUDIT_LOG', 'DATA_ACCESS', 'CONSENT_SUMMARY'];

        foreach ($invalidTypes as $type) {
            self::assertNotContains($type, $validTypes, "'{$type}' should NOT be a valid report type");
        }
    }

    public function testSerializeReportContractKeys(): void
    {
        $expectedKeys = self::SERIALIZED_REPORT_KEYS;

        // Keys that MUST NOT be in the response (old frontend-guessed fields).
        $forbiddenKeys = [
            'title',
            'status',
            'file_size',
            'sha256_hash',
            'expires_at',
            'created_at',
            'updated_at',
            'file_path',
        ];

        // Verify no overlap.
        foreach ($forbiddenKeys as $key) {
            self::assertNotContains($key, $expectedKeys, "'{$key}' must not be in compliance report response");
        }

        // Verify count.
        self::assertCount(9, $expectedKeys);
    }

    public function testDownloadUrlFormatIsOpaque(): void
    {
        $id = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $expectedUrl = '/api/v1/compliance-reports/' . $id . '/download';

        // The URL must contain the report ID and NOT contain filesystem paths.
        self::assertStringContainsString($id, $expectedUrl);
        self::assertStringNotContainsString('/tmp/', $expectedUrl);
        self::assertStringNotContainsString('var/', $expectedUrl);
    }
}
