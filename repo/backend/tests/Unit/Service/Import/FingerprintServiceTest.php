<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Import;

use App\Service\Import\FingerprintService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FingerprintService::class)]
final class FingerprintServiceTest extends TestCase
{
    private FingerprintService $service;

    protected function setUp(): void
    {
        $this->service = new FingerprintService();
    }

    public function testFingerprintIsSha256With64HexChars(): void
    {
        $fingerprint = $this->service->computeFingerprint(
            'software engineer',
            'acme corp',
            'new york',
            'We are looking for a talented engineer to join our team.',
        );

        self::assertSame(64, strlen($fingerprint));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $fingerprint);
    }

    public function testFingerprintIsDeterministic(): void
    {
        $fp1 = $this->service->computeFingerprint('title', 'company', 'location', 'body text');
        $fp2 = $this->service->computeFingerprint('title', 'company', 'location', 'body text');

        self::assertSame($fp1, $fp2);
    }

    public function testFingerprintUsesFirst200BodyCharsOnly(): void
    {
        $body200 = str_repeat('a', 200);
        $bodyLong = str_repeat('a', 200) . 'EXTRA_CHARACTERS_IGNORED';

        $fp200 = $this->service->computeFingerprint('title', 'company', 'location', $body200);
        $fpLong = $this->service->computeFingerprint('title', 'company', 'location', $bodyLong);

        self::assertSame($fp200, $fpLong, 'Characters beyond 200 should be ignored.');
    }

    public function testBodyShorterThan200IsUsedFully(): void
    {
        $bodyShort = 'short body';
        $bodyShortPadded = 'short body' . str_repeat('x', 1);

        $fpShort = $this->service->computeFingerprint('title', 'company', 'location', $bodyShort);
        $fpPadded = $this->service->computeFingerprint('title', 'company', 'location', $bodyShortPadded);

        self::assertNotSame($fpShort, $fpPadded, 'Different short bodies should produce different fingerprints.');
    }

    public function testDifferentInputsProduceDifferentFingerprints(): void
    {
        $fp1 = $this->service->computeFingerprint('title a', 'company a', 'location a', 'body a');
        $fp2 = $this->service->computeFingerprint('title b', 'company b', 'location b', 'body b');

        self::assertNotSame($fp1, $fp2);
    }

    public function testNullFieldsAreHandledGracefully(): void
    {
        $fingerprint = $this->service->computeFingerprint('title', null, null, null);

        self::assertSame(64, strlen($fingerprint));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $fingerprint);
    }

    public function testNullBodyVsEmptyBodyProduceSameFingerprint(): void
    {
        $fpNull = $this->service->computeFingerprint('title', 'company', 'location', null);
        $fpEmpty = $this->service->computeFingerprint('title', 'company', 'location', '');

        self::assertSame($fpNull, $fpEmpty, 'Null body and empty body should hash identically.');
    }

    public function testDifferentTitlesSameBodiesAreDifferent(): void
    {
        $fp1 = $this->service->computeFingerprint('title one', 'company', 'location', 'same body');
        $fp2 = $this->service->computeFingerprint('title two', 'company', 'location', 'same body');

        self::assertNotSame($fp1, $fp2);
    }
}
