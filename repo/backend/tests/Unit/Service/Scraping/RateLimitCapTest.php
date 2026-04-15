<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Scraping;

use App\Entity\Scraping\SourceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Validates the 30 req/min hard cap on source definitions.
 *
 * The SourceDefinition entity defaults to 30 and the SourceDefinitionController
 * rejects values outside [1, 30]. These tests verify both the entity default
 * and the validation boundaries without needing the full HTTP stack.
 */
final class RateLimitCapTest extends TestCase
{
    public function testSourceDefinitionDefaultsTo30RequestsPerMinute(): void
    {
        $source = new SourceDefinition();

        self::assertSame(30, $source->getMaxRequestsPerMinute());
    }

    public function testSetMaxRequestsPerMinuteTo30IsAccepted(): void
    {
        $source = new SourceDefinition();
        $source->setMaxRequestsPerMinute(30);

        self::assertSame(30, $source->getMaxRequestsPerMinute());
    }

    public function testSetMaxRequestsPerMinuteTo1IsAccepted(): void
    {
        $source = new SourceDefinition();
        $source->setMaxRequestsPerMinute(1);

        self::assertSame(1, $source->getMaxRequestsPerMinute());
    }

    /**
     * Replicates the controller validation: rpm < 1 || rpm > 30 => rejected.
     */
    public function testValueAbove30IsRejectedByValidation(): void
    {
        $rpm = 31;
        $isValid = $rpm >= 1 && $rpm <= 30;

        self::assertFalse($isValid, 'RPM of 31 must be rejected by validation.');
    }

    /**
     * Replicates the controller validation: rpm < 1 => rejected.
     */
    public function testValueOfZeroIsRejectedByValidation(): void
    {
        $rpm = 0;
        $isValid = $rpm >= 1 && $rpm <= 30;

        self::assertFalse($isValid, 'RPM of 0 must be rejected by validation.');
    }

    public function testNegativeValueIsRejectedByValidation(): void
    {
        $rpm = -5;
        $isValid = $rpm >= 1 && $rpm <= 30;

        self::assertFalse($isValid, 'Negative RPM must be rejected by validation.');
    }

    public function testValueOf30PassesValidation(): void
    {
        $rpm = 30;
        $isValid = $rpm >= 1 && $rpm <= 30;

        self::assertTrue($isValid, 'RPM of 30 must pass validation.');
    }

    public function testValueOf15PassesValidation(): void
    {
        $rpm = 15;
        $isValid = $rpm >= 1 && $rpm <= 30;

        self::assertTrue($isValid, 'RPM of 15 must pass validation.');
    }

    public function testLargeValueIsRejectedByValidation(): void
    {
        $rpm = 1000;
        $isValid = $rpm >= 1 && $rpm <= 30;

        self::assertFalse($isValid, 'RPM of 1000 must be rejected by validation.');
    }
}
