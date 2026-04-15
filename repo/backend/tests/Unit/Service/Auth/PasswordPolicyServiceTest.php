<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Auth;

use App\Service\Auth\PasswordPolicyService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordPolicyService::class)]
final class PasswordPolicyServiceTest extends TestCase
{
    private PasswordPolicyService $service;

    protected function setUp(): void
    {
        $this->service = new PasswordPolicyService();
    }

    public function testValidPasswordPassesAllRules(): void
    {
        $errors = $this->service->validate('Str0ng!Pass99');

        self::assertSame([], $errors);
    }

    public function testTooShortPasswordFails(): void
    {
        // 11 characters -- one short of the 12-char minimum.
        $errors = $this->service->validate('Str0ng!Pa99');

        self::assertCount(1, $errors);
        self::assertStringContainsString('at least 12 characters', $errors[0]);
    }

    public function testMissingUppercaseFails(): void
    {
        $errors = $this->service->validate('str0ng!pass99');

        self::assertNotEmpty($errors);
        self::assertTrue(
            $this->containsMessage($errors, 'uppercase'),
            'Expected an error about missing uppercase letter.',
        );
    }

    public function testMissingLowercaseFails(): void
    {
        $errors = $this->service->validate('STR0NG!PASS99');

        self::assertNotEmpty($errors);
        self::assertTrue(
            $this->containsMessage($errors, 'lowercase'),
            'Expected an error about missing lowercase letter.',
        );
    }

    public function testMissingDigitFails(): void
    {
        $errors = $this->service->validate('Strong!Passwrd');

        self::assertNotEmpty($errors);
        self::assertTrue(
            $this->containsMessage($errors, 'digit'),
            'Expected an error about missing digit.',
        );
    }

    public function testMissingSymbolFails(): void
    {
        $errors = $this->service->validate('Str0ngPassw99');

        self::assertNotEmpty($errors);
        self::assertTrue(
            $this->containsMessage($errors, 'symbol'),
            'Expected an error about missing symbol.',
        );
    }

    public function testMultipleViolationsReturnsAllErrors(): void
    {
        // All lowercase, no digit, no symbol, too short (5 chars).
        $errors = $this->service->validate('abcde');

        // Should report: too short, missing uppercase, missing digit, missing symbol
        self::assertCount(4, $errors);
        self::assertTrue($this->containsMessage($errors, 'at least 12 characters'));
        self::assertTrue($this->containsMessage($errors, 'uppercase'));
        self::assertTrue($this->containsMessage($errors, 'digit'));
        self::assertTrue($this->containsMessage($errors, 'symbol'));
    }

    public function testExactlyMinimumLengthPasses(): void
    {
        // Exactly 12 characters, all rules satisfied.
        $errors = $this->service->validate('Abcdefgh!1kl');

        self::assertSame([], $errors);
    }

    /**
     * Check whether any error message contains the given substring (case-insensitive).
     *
     * @param string[] $errors
     */
    private function containsMessage(array $errors, string $substring): bool
    {
        foreach ($errors as $error) {
            if (str_contains(strtolower($error), strtolower($substring))) {
                return true;
            }
        }

        return false;
    }
}
