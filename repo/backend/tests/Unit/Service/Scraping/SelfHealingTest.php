<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Scraping;

use App\Entity\Scraping\ProxyPool;
use App\Entity\Scraping\SourceDefinition;
use App\Service\Scraping\ProxyRotationService;
use App\Service\Scraping\SelfHealingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SelfHealingService::class)]
final class SelfHealingTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private ProxyRotationService&MockObject $proxyRotation;
    private SelfHealingService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->proxyRotation = $this->createMock(ProxyRotationService::class);

        $this->em->method('persist')->willReturnCallback(function () {});
        $this->em->method('flush')->willReturnCallback(function () {});

        $this->service = new SelfHealingService($this->em, $this->proxyRotation);
    }

    public function testCaptchaDetectionDegradesToDegraded(): void
    {
        $source = $this->createSource('ACTIVE');

        $this->service->evaluate($source, 'CAPTCHA');

        self::assertSame('DEGRADED', $source->getStatus());
    }

    public function testActiveSourceDegradedOnAnyAdverseEvent(): void
    {
        $source = $this->createSource('ACTIVE');

        $this->service->evaluate($source, 'TIMEOUT');

        self::assertSame('DEGRADED', $source->getStatus());
    }

    public function testDegradedSourceTriesProxySwitchFirst(): void
    {
        $source = $this->createSource('DEGRADED');

        $proxy = $this->createMock(ProxyPool::class);
        $proxy->method('getProxyUrl')->willReturn('http://proxy2.example.com:8080');
        $this->proxyRotation->method('getNextProxy')->willReturn($proxy);

        $this->service->evaluate($source, 'CAPTCHA');

        // With a proxy available, source stays DEGRADED (given another chance).
        self::assertSame('DEGRADED', $source->getStatus());
    }

    public function testDegradedSourcePausesWhenNoProxiesAvailable(): void
    {
        $source = $this->createSource('DEGRADED');

        $this->proxyRotation->method('getNextProxy')->willReturn(null);

        $this->service->evaluate($source, 'CAPTCHA');

        self::assertSame('PAUSED', $source->getStatus());
        self::assertNotNull($source->getPausedUntil());

        // paused_until should be approximately 60 minutes from now.
        $now = new \DateTimeImmutable();
        $diff = $source->getPausedUntil()->getTimestamp() - $now->getTimestamp();
        // Allow 5-second tolerance for test execution time.
        self::assertGreaterThan(3500, $diff);
        self::assertLessThanOrEqual(3605, $diff);
    }

    public function testPausedSourceDisabledOnFurtherFailure(): void
    {
        $source = $this->createSource('PAUSED');

        $this->service->evaluate($source, 'CAPTCHA');

        self::assertSame('DISABLED', $source->getStatus());
    }

    public function testDisabledSourceRemainsDisabledOnEvaluate(): void
    {
        $source = $this->createSource('DISABLED');

        $this->service->evaluate($source, 'CAPTCHA');

        // No further action on a DISABLED source.
        self::assertSame('DISABLED', $source->getStatus());
    }

    public function testDisabledSourceCanBeManuallyReEnabled(): void
    {
        $source = $this->createSource('DISABLED');

        // Simulate manual re-enable (as the resume controller does).
        $source->setStatus('ACTIVE');
        $source->setPausedUntil(null);
        $source->setPauseReason(null);

        self::assertSame('ACTIVE', $source->getStatus());
        self::assertNull($source->getPausedUntil());
    }

    public function testDegradationChainFullSequence(): void
    {
        $source = $this->createSource('ACTIVE');
        $this->proxyRotation->method('getNextProxy')->willReturn(null);

        // Step 1: ACTIVE -> DEGRADED
        $this->service->evaluate($source, 'CAPTCHA');
        self::assertSame('DEGRADED', $source->getStatus());

        // Step 2: DEGRADED -> PAUSED (no proxies)
        $this->service->evaluate($source, 'CAPTCHA');
        self::assertSame('PAUSED', $source->getStatus());

        // Step 3: PAUSED -> DISABLED
        $this->service->evaluate($source, 'CAPTCHA');
        self::assertSame('DISABLED', $source->getStatus());
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createSource(string $status): SourceDefinition
    {
        $source = new SourceDefinition();
        $source->setName('test-source-' . bin2hex(random_bytes(4)));
        $source->setBaseUrl('https://example.com');
        $source->setScrapeType('HTML');
        $source->setStatus($status);

        return $source;
    }
}
