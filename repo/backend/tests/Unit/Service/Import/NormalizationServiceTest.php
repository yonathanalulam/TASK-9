<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Import;

use App\Service\Import\NormalizationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NormalizationService::class)]
final class NormalizationServiceTest extends TestCase
{
    private NormalizationService $service;

    protected function setUp(): void
    {
        $this->service = new NormalizationService();
    }

    public function testLowercaseConversion(): void
    {
        $result = $this->service->normalize('Hello WORLD');

        self::assertSame('hello world', $result);
    }

    public function testWhitespaceTrimming(): void
    {
        $result = $this->service->normalize('  hello  ');

        self::assertSame('hello', $result);
    }

    public function testRepeatedSpaceCollapsing(): void
    {
        $result = $this->service->normalize('hello    world   foo');

        self::assertSame('hello world foo', $result);
    }

    public function testPunctuationRemoval(): void
    {
        $result = $this->service->normalize('Hello, World! How are you?');

        self::assertSame('hello world how are you', $result);
    }

    public function testHyphensArePreserved(): void
    {
        $result = $this->service->normalize('San-Francisco');

        self::assertSame('san-francisco', $result);
    }

    public function testUnderscoresArePreserved(): void
    {
        $result = $this->service->normalize('some_value');

        self::assertSame('some_value', $result);
    }

    public function testCombinedNormalization(): void
    {
        $result = $this->service->normalize('  Hello,  WORLD!   How   Are  You?  ');

        self::assertSame('hello world how are you', $result);
    }

    public function testEmptyStringReturnsEmpty(): void
    {
        $result = $this->service->normalize('');

        self::assertSame('', $result);
    }

    public function testUnicodeCharactersPreserved(): void
    {
        $result = $this->service->normalize('Cafe Muenchen');

        self::assertSame('cafe muenchen', $result);
    }

    public function testTabsAndNewlinesCollapsed(): void
    {
        $result = $this->service->normalize("hello\t\tworld\nfoo");

        self::assertSame('hello world foo', $result);
    }
}
