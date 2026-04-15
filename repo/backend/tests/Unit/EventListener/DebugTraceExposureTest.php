<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\ApiExceptionListener;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that the ApiExceptionListener gates debug trace exposure via
 * the `debug` flag (kernel.debug) rather than the old environment-based check.
 */
final class DebugTraceExposureTest extends TestCase
{
    public function testApiExceptionListenerUsesDebugFlag(): void
    {
        $reflection = new \ReflectionClass(ApiExceptionListener::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        $params = $constructor->getParameters();

        // First parameter should be the debug flag (bool)
        $debugParam = null;
        foreach ($params as $p) {
            if ($p->getName() === 'debug') {
                $debugParam = $p;
                break;
            }
        }

        self::assertNotNull($debugParam, 'Constructor must have a "debug" parameter');
        self::assertSame('bool', $debugParam->getType()?->getName());
    }

    public function testDebugFlagGatesTraceExposure(): void
    {
        // Verify the class no longer uses 'environment' for gating
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/src/EventListener/ApiExceptionListener.php'
        );

        // Should NOT contain the old environment-based check
        self::assertStringNotContainsString("in_array(\$this->environment", $source);
        // Should contain the debug-based check
        self::assertStringContainsString('$this->debug', $source);
    }
}
