<?php

namespace App\Enums;

/**
 * Common Platform Layer — what a one-time email code is for. Each purpose has
 * its own table, lifetime and wording; codes are never shared between them.
 */
enum AuthCodePurpose: string
{
    case PasswordReset = 'password_reset';
    case EmailVerification = 'email_verification';

    /** Table holding the hashed code, keyed by email address. */
    public function table(): string
    {
        return match ($this) {
            self::PasswordReset => 'password_reset_tokens',
            self::EmailVerification => 'email_verification_codes',
        };
    }

    /** How long a freshly issued code stays valid. */
    public function minutes(): int
    {
        return match ($this) {
            self::PasswordReset => 15,
            self::EmailVerification => 60 * 24,
        };
    }

    /** Wrong guesses allowed before the code is thrown away. */
    public function maxAttempts(): int
    {
        return 5;
    }

    public function label(): string
    {
        return match ($this) {
            self::PasswordReset => 'password reset',
            self::EmailVerification => 'email verification',
        };
    }
}
