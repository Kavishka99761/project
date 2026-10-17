<?php

namespace Tests\Feature;

use App\Enums\AuthCodePurpose;
use App\Mail\EmailVerificationCodeMail;
use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Common Platform Layer — registration, email verification and password
 * recovery behave the way a deployed service behaves: codes travel by email
 * only, responses leak nothing, and codes expire, lock and die after use.
 */
class AuthRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function student(array $attributes = []): User
    {
        $user = User::create([
            'name' => 'Ayesha Perera',
            'email' => 'ayesha@acadealert.lk',
            'password' => 'Study2026x',
        ]);
        $user->settings()->create(UserSetting::defaults());

        // email_verified_at is deliberately not mass-assignable (nobody can
        // verify themselves by posting it), so set it the long way here.
        if ($attributes) {
            $user->forceFill($attributes)->save();
        }

        return $user;
    }

    /** Pull the code out of the mail that was "sent". */
    private function mailedCode(string $mailable): string
    {
        $sent = Mail::sent($mailable);
        $this->assertNotEmpty($sent, "No {$mailable} was sent.");

        return $sent->first()->code;
    }

    public function test_registration_creates_an_unverified_account_and_emails_a_code(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Nimal Silva',
            'email' => 'nimal@acadealert.lk',
            'password' => 'Study2026x',
            'password_confirmation' => 'Study2026x',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email_verified', false)
            ->assertJsonPath('email_verification_sent', true)
            ->assertJsonStructure(['token', 'user']);

        Mail::assertSent(EmailVerificationCodeMail::class, fn ($mail) => $mail->hasTo('nimal@acadealert.lk'));
        $this->assertDatabaseHas('email_verification_codes', ['email' => 'nimal@acadealert.lk']);
        // Only a hash of the code is stored.
        $this->assertNotSame(
            $this->mailedCode(EmailVerificationCodeMail::class),
            DB::table('email_verification_codes')->where('email', 'nimal@acadealert.lk')->value('code'),
        );
    }

    public function test_registration_rejects_a_duplicate_email_and_a_weak_password(): void
    {
        $this->student();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Someone Else',
            'email' => 'ayesha@acadealert.lk',
            'password' => 'Study2026x',
            'password_confirmation' => 'Study2026x',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Nimal Silva',
            'email' => 'nimal@acadealert.lk',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_the_emailed_code_confirms_the_address_once(): void
    {
        Mail::fake();
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Nimal Silva',
            'email' => 'nimal@acadealert.lk',
            'password' => 'Study2026x',
            'password_confirmation' => 'Study2026x',
        ])->assertCreated();

        $code = $this->mailedCode(EmailVerificationCodeMail::class);

        // A pasted "123 456" is accepted.
        $this->postJson('/api/v1/auth/verify-email', [
            'email' => 'nimal@acadealert.lk',
            'code' => substr($code, 0, 3).' '.substr($code, 3),
        ])->assertOk()->assertJsonPath('user.email_verified', true);

        $this->assertNotNull(User::where('email', 'nimal@acadealert.lk')->first()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_codes', ['email' => 'nimal@acadealert.lk']);

        $this->postJson('/api/v1/auth/verify-email', ['email' => 'nimal@acadealert.lk', 'code' => $code])
            ->assertOk()->assertJsonPath('already_verified', true);
    }

    public function test_forgot_password_answers_the_same_for_any_email_and_returns_no_secret(): void
    {
        Mail::fake();
        $this->student();

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ayesha@acadealert.lk'])->assertOk();
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ghost@nowhere.lk'])->assertOk();

        $this->assertSame($known->json('message'), $unknown->json('message'));
        $this->assertSame(['message'], array_keys($known->json()));

        Mail::assertSent(PasswordResetCodeMail::class, 1);
        Mail::assertNotSent(PasswordResetCodeMail::class, fn ($mail) => $mail->hasTo('ghost@nowhere.lk'));
    }

    public function test_the_reset_code_sets_a_new_password_and_signs_other_devices_out(): void
    {
        Mail::fake();
        $user = $this->student();
        $user->createToken('old device');
        $this->assertSame(1, $user->tokens()->count());

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $code = $this->mailedCode(PasswordResetCodeMail::class);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'Fresh2026pass',
            'password_confirmation' => 'Fresh2026pass',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('Fresh2026pass', $user->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        Mail::assertSent(PasswordChangedMail::class, fn ($mail) => $mail->hasTo($user->email));

        // The code cannot be replayed, and the old password is dead.
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'Another2026x',
            'password_confirmation' => 'Another2026x',
        ])->assertStatus(422)->assertJsonValidationErrors('code');

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Study2026x'])
            ->assertStatus(422);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Fresh2026pass'])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_five_wrong_codes_destroy_the_code(): void
    {
        Mail::fake();
        $user = $this->student();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $code = $this->mailedCode(PasswordResetCodeMail::class);
        $wrong = str_pad((string) ((((int) $code) + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/reset-password', [
                'email' => $user->email,
                'code' => $wrong,
                'password' => 'Fresh2026pass',
                'password_confirmation' => 'Fresh2026pass',
            ])->assertStatus(422)->assertJsonValidationErrors('code');
        }

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'Fresh2026pass',
            'password_confirmation' => 'Fresh2026pass',
        ])->assertStatus(422)->assertJsonValidationErrors('code');

        $this->assertTrue(Hash::check('Study2026x', $user->refresh()->password));
    }

    public function test_an_expired_code_is_refused(): void
    {
        Mail::fake();
        $user = $this->student();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $code = $this->mailedCode(PasswordResetCodeMail::class);

        DB::table('password_reset_tokens')->where('email', $user->email)->update([
            'created_at' => now()->subMinutes(AuthCodePurpose::PasswordReset->minutes() + 1),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'Fresh2026pass',
            'password_confirmation' => 'Fresh2026pass',
        ])->assertStatus(422)->assertJsonValidationErrors('code');

        $this->assertTrue(Hash::check('Study2026x', $user->refresh()->password));
    }

    public function test_resending_verification_is_quiet_about_unknown_and_verified_addresses(): void
    {
        Mail::fake();
        $this->student(['email_verified_at' => now()]);

        $this->postJson('/api/v1/auth/resend-verification', ['email' => 'ayesha@acadealert.lk'])->assertOk();
        $this->postJson('/api/v1/auth/resend-verification', ['email' => 'ghost@nowhere.lk'])->assertOk();

        Mail::assertNotSent(EmailVerificationCodeMail::class);
    }
}
