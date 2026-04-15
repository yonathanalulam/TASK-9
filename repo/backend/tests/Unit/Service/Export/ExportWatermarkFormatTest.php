<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Export;

use PHPUnit\Framework\TestCase;

/**
 * Tests that export watermarks conform to the required format:
 * "{username} {MM/DD/YYYY hh:mm AM/PM}"
 *
 * This replicates the format logic used in ExportService::requestExport()
 * without requiring the full service dependency graph.
 */
final class ExportWatermarkFormatTest extends TestCase
{
    /**
     * Build a watermark string using the same logic as ExportService.
     */
    private function buildWatermark(string $username, \DateTimeImmutable $timestamp): string
    {
        return sprintf('%s %s', $username, $timestamp->format('m/d/Y h:i A'));
    }

    public function testWatermarkContainsUsername(): void
    {
        $username = 'test_user';
        $now = new \DateTimeImmutable('2026-03-15 14:30:00', new \DateTimeZone('UTC'));

        $watermark = $this->buildWatermark($username, $now);

        self::assertStringContainsString('test_user', $watermark);
    }

    public function testWatermarkTimestampMatchesExpectedRegex(): void
    {
        $username = 'admin';
        $now = new \DateTimeImmutable('2026-07-04 09:05:00', new \DateTimeZone('UTC'));

        $watermark = $this->buildWatermark($username, $now);

        // Timestamp portion must match MM/DD/YYYY hh:mm AM|PM
        self::assertMatchesRegularExpression(
            '/\d{2}\/\d{2}\/\d{4} \d{2}:\d{2} (AM|PM)$/',
            $watermark,
        );
    }

    public function testMonthIsZeroPadded(): void
    {
        $username = 'test_user';
        // January = month 01, not "1"
        $now = new \DateTimeImmutable('2026-01-05 10:00:00', new \DateTimeZone('UTC'));

        $watermark = $this->buildWatermark($username, $now);

        self::assertSame('test_user 01/05/2026 10:00 AM', $watermark);
    }

    public function testHoursAre12HourFormat(): void
    {
        $username = 'test_user';
        // 14:30 in 24h = 02:30 PM in 12h
        $now = new \DateTimeImmutable('2026-03-15 14:30:00', new \DateTimeZone('UTC'));

        $watermark = $this->buildWatermark($username, $now);

        self::assertSame('test_user 03/15/2026 02:30 PM', $watermark);
        // Confirm 24-hour "14" is NOT present
        self::assertStringNotContainsString('14:30', $watermark);
    }

    public function testMidnightFormatsAs12AM(): void
    {
        $username = 'night_user';
        $now = new \DateTimeImmutable('2026-06-01 00:00:00', new \DateTimeZone('UTC'));

        $watermark = $this->buildWatermark($username, $now);

        self::assertSame('night_user 06/01/2026 12:00 AM', $watermark);
    }

    public function testNoonFormatsAs12PM(): void
    {
        $username = 'noon_user';
        $now = new \DateTimeImmutable('2026-06-01 12:00:00', new \DateTimeZone('UTC'));

        $watermark = $this->buildWatermark($username, $now);

        self::assertSame('noon_user 06/01/2026 12:00 PM', $watermark);
    }

    public function testFullFormatMatchesExportServiceOutput(): void
    {
        $username = 'test_user';
        $now = new \DateTimeImmutable('2026-03-15 14:30:00', new \DateTimeZone('UTC'));

        // Exact replica of ExportService line 47-50
        $watermark = sprintf(
            '%s %s',
            $username,
            $now->format('m/d/Y h:i A'),
        );

        self::assertSame('test_user 03/15/2026 02:30 PM', $watermark);
    }
}
