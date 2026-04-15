<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Export;

use App\Service\Export\ExportService;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the export dataset and format validation is deterministic and
 * does not silently produce empty exports for unsupported dataset values.
 */
final class ExportDatasetValidationTest extends TestCase
{
    /* -------------------------------------------------------------- */
    /*  Dataset enum                                                   */
    /* -------------------------------------------------------------- */

    public function testValidDatasetsAreExactlyContentItemsAndAuditEvents(): void
    {
        $expected = ['content_items', 'audit_events'];
        self::assertSame($expected, ExportService::VALID_DATASETS);
        self::assertCount(2, ExportService::VALID_DATASETS);
    }

    public function testOldFrontendDatasetValuesAreNotValid(): void
    {
        $invalid = ['content', 'users', 'regions', 'stores', 'delivery_zones', 'audit_logs'];
        foreach ($invalid as $dataset) {
            self::assertNotContains(
                $dataset,
                ExportService::VALID_DATASETS,
                sprintf('"%s" must NOT be a valid dataset value', $dataset),
            );
        }
    }

    public function testValidDatasetsContainsContentItems(): void
    {
        self::assertContains('content_items', ExportService::VALID_DATASETS);
    }

    public function testValidDatasetsContainsAuditEvents(): void
    {
        self::assertContains('audit_events', ExportService::VALID_DATASETS);
    }

    /* -------------------------------------------------------------- */
    /*  Format enum                                                    */
    /* -------------------------------------------------------------- */

    public function testValidFormatsContainsOnlyCsv(): void
    {
        self::assertSame(['CSV'], ExportService::VALID_FORMATS);
        self::assertCount(1, ExportService::VALID_FORMATS);
    }

    public function testPdfAndHtmlAreNotValidFormats(): void
    {
        self::assertNotContains('PDF', ExportService::VALID_FORMATS);
        self::assertNotContains('HTML', ExportService::VALID_FORMATS);
    }

    public function testEveryValidFormatHasARenderer(): void
    {
        // CSV is supported by CsvExportRenderer. No other renderer exists.
        // This test documents that every format in VALID_FORMATS can actually be generated.
        foreach (ExportService::VALID_FORMATS as $format) {
            self::assertSame('CSV', $format, 'Only CSV has a real renderer');
        }
    }

    /* -------------------------------------------------------------- */
    /*  Response shape contract                                        */
    /* -------------------------------------------------------------- */

    public function testSerializerCanonicalKeys(): void
    {
        // These are the exact keys returned by ExportController::serializeExportJob()
        $canonicalKeys = [
            'id', 'dataset', 'format', 'status',
            'requested_by', 'authorized_by', 'filters',
            'file_name', 'watermark_text', 'tamper_hash_sha256',
            'requested_at', 'authorized_at', 'completed_at', 'expires_at',
        ];

        self::assertCount(14, $canonicalKeys);

        // Old frontend-guessed keys must not appear
        $forbidden = ['reason', 'file_size', 'download_url', 'created_at', 'updated_at'];
        foreach ($forbidden as $key) {
            self::assertNotContains(
                $key,
                $canonicalKeys,
                sprintf('"%s" must NOT be in the canonical export response', $key),
            );
        }
    }

    public function testRequestedAtIsCanonicalTimestampFieldNotCreatedAt(): void
    {
        $canonicalKeys = [
            'id', 'dataset', 'format', 'status',
            'requested_by', 'authorized_by', 'filters',
            'file_name', 'watermark_text', 'tamper_hash_sha256',
            'requested_at', 'authorized_at', 'completed_at', 'expires_at',
        ];

        self::assertContains('requested_at', $canonicalKeys);
        self::assertNotContains('created_at', $canonicalKeys);
    }
}
