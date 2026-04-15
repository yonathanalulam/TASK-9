<?php

declare(strict_types=1);

namespace App\Service\Auth;

class PasswordPolicyService
{
    private const int MIN_LENGTH = 12;

    /**
     * Validate a plain-text password against the policy rules.
     *
     * @return string[] Array of error messages (empty when valid)
     */
    public function validate(string $plainPassword): array
    {
        $errors = [];

        if (mb_strlen($plainPassword) < self::MIN_LENGTH) {
            $errors[] = 'Password must be at least 12 characters long.';
        }

        if (preg_match('/[A-Z]/', $plainPassword) !== 1) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }

        if (preg_match('/[a-z]/', $plainPassword) !== 1) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }

        if (preg_match('/[0-9]/', $plainPassword) !== 1) {
            $errors[] = 'Password must contain at least one digit.';
        }

        if (preg_match('/[^a-zA-Z0-9]/', $plainPassword) !== 1) {
            $errors[] = 'Password must contain at least one symbol.';
        }

        return $errors;
    }
}
