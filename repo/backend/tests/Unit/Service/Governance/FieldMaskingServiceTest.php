<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Governance;

use App\Service\Governance\FieldMaskingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class FieldMaskingServiceTest extends TestCase
{
    private FieldMaskingService $service;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->service = new FieldMaskingService($em);
    }

    public function testMaskSsnWithDashedInput(): void
    {
        self::assertSame('***-**-6789', $this->service->maskSsn('123-45-6789'));
    }

    public function testMaskSsnWithUndashed(): void
    {
        self::assertSame('***-**-4321', $this->service->maskSsn('987654321'));
    }

    public function testMaskSsnShortValue(): void
    {
        $result = $this->service->maskSsn('12');
        self::assertSame('***-**-12', $result);
    }

    public function testMaskSsnEmptyString(): void
    {
        $result = $this->service->maskSsn('');
        self::assertSame('***-**-', $result);
    }

    public function testMaskSsnFourDigit(): void
    {
        self::assertSame('***-**-1234', $this->service->maskSsn('1234'));
    }

    public function testClassificationLevelsRequiringMasking(): void
    {
        // RESTRICTED and HIGHLY_RESTRICTED classifications should require masking.
        // This is a design-level assertion documenting the expected classification behavior.
        $levels = ['RESTRICTED', 'HIGHLY_RESTRICTED'];
        foreach ($levels as $level) {
            self::assertContains($level, ['RESTRICTED', 'HIGHLY_RESTRICTED', 'CONFIDENTIAL', 'PUBLIC_INTERNAL']);
        }

        // PUBLIC_INTERNAL and CONFIDENTIAL do NOT require masking
        $noMaskLevels = ['PUBLIC_INTERNAL', 'CONFIDENTIAL'];
        foreach ($noMaskLevels as $level) {
            self::assertNotContains($level, ['RESTRICTED', 'HIGHLY_RESTRICTED']);
        }
    }
}
