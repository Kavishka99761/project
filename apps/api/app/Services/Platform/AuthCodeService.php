<?php

namespace App\Services\Platform;

use App\Enums\AuthCodePurpose;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Common Platform Layer — issuing and checking the 6-digit codes emailed for
 * password resets and email verification.
 *
 * Rules, all enforced here so every caller behaves the same way:
 *  - only a hash of the code is stored, so a database dump cannot be replayed;
 *  - one live code per email per purpose (issuing again replaces the old one);
 *  - a code expires (see AuthCodePurpose::minutes());
 *  - a code is deleted after 5 wrong guesses, which is what keeps a 6-digit
 *    secret safe: an attacker gets 5 tries out of a million, and the route is
 *    rate-limited per email on top of that;
 *  - a successful check consumes the code, so it cannot be used twice.
 */
class AuthCodeService
{
    public const OK = 'ok';

    public const INVALID = 'invalid';

    public const EXPIRED = 'expired';

    public const LOCKED = 'locked';

    public const MISSING = 'missing';

    /** Issue a fresh code, replacing any previous one, and return the plain digits. */
    public function issue(AuthCodePurpose $purpose, string $email): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table($purpose->table())->updateOrInsert(
            ['email' => $email],
            ['code' => Hash::make($code), 'attempts' => 0, 'created_at' => now()],
        );

        return $code;
    }

    /**
     * Check a code and consume it when it is correct.
     *
     * @return string one of the OK / INVALID / EXPIRED / LOCKED / MISSING constants
     */
    public function consume(AuthCodePurpose $purpose, string $email, string $code): string
    {
        $table = $purpose->table();
        $row = DB::table($table)->where('email', $email)->first();

        if (! $row) {
            return self::MISSING;
        }

        if (now()->diffInMinutes($row->created_at, true) > $purpose->minutes()) {
            $this->forget($purpose, $email);

            return self::EXPIRED;
        }

        if (! Hash::check($code, $row->code)) {
            $attempts = (int) ($row->attempts ?? 0) + 1;

            if ($attempts >= $purpose->maxAttempts()) {
                $this->forget($purpose, $email);

                return self::LOCKED;
            }

            DB::table($table)->where('email', $email)->update(['attempts' => $attempts]);

            return self::INVALID;
        }

        $this->forget($purpose, $email);

        return self::OK;
    }

    /** Drop any pending code (used after success, expiry or too many guesses). */
    public function forget(AuthCodePurpose $purpose, string $email): void
    {
        DB::table($purpose->table())->where('email', $email)->delete();
    }

    /** Validation message for a failed check, ready to throw back at the client. */
    public function message(string $status, AuthCodePurpose $purpose): string
    {
        return match ($status) {
            self::EXPIRED => 'That code has expired. Request a new one.',
            self::LOCKED => 'Too many incorrect codes. Request a new one.',
            self::MISSING => 'No '.$purpose->label().' code is pending for that email. Request a new one.',
            default => 'That code is not correct. Check the email and try again.',
        };
    }
}
