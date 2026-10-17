<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Enums\AuthCodePurpose;
use App\Enums\ModuleKey;
use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ForgotPasswordRequest;
use App\Http\Requests\Platform\LoginRequest;
use App\Http\Requests\Platform\RegisterRequest;
use App\Http\Requests\Platform\ResendVerificationRequest;
use App\Http\Requests\Platform\ResetPasswordRequest;
use App\Http\Requests\Platform\VerifyEmailRequest;
use App\Http\Resources\Platform\UserResource;
use App\Mail\EmailVerificationCodeMail;
use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Firebase\FirebaseService;
use App\Services\Platform\AuthCodeService;
use App\Services\Platform\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Common Platform Layer — authentication: register, login, logout, email
 * verification and password recovery. Sanctum personal access tokens (one per
 * device) with an expiry; failed logins are throttled and recorded in the
 * audit trail.
 *
 * Both recovery flows behave the way a deployed service does: a 6-digit code
 * is emailed to the address, no secret is ever returned in an API response,
 * and the endpoints answer identically whether or not the account exists.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly FirebaseService $firebase,
        private readonly NotificationService $notifications,
        private readonly AuthCodeService $codes,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create($request->safe()->only(['name', 'email', 'program', 'university']) + [
                'password' => $request->validated('password'),
            ]);
            $user->settings()->create(UserSetting::defaults());

            return $user;
        });

        activity()->asUser($user->id)->action('auth.registered')->describe("Created account {$user->email}")->on($user);
        $this->notifications->send($user, ModuleKey::Platform, 'Welcome to AcadeAlert, '.Str::before($user->name, ' ').'!',
            'Confirm your email address, then add your modules and upload your first lecture notes.',
            NotificationType::Success, '/modules', 'stars');
        $this->firebase->mirrorProfile($user);
        $this->sendVerificationCode($user);

        // The account works straight away; the client nudges the student to
        // confirm the address, which is what unlocks reminder mail and recovery.
        return response()->json($this->tokenPayload($user, $request) + ['email_verification_sent' => true], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            activity()->action('auth.login_failed')->describe('Failed sign-in attempt for '.$request->validated('email'))
                ->with(['email' => $request->validated('email')]);
            if ($user) {
                activity()->asUser($user->id);
            }

            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        activity()->asUser($user->id)->action('auth.login')->describe('Signed in from '.$this->deviceName($request))->on($user);
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $this->firebase->mirrorProfile($user);

        return response()->json($this->tokenPayload($user, $request));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        activity()->action('auth.logout')->describe('Signed out');

        return response()->json(['message' => 'Signed out successfully.']);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user()->load('settings'));
    }

    /**
     * Step 1 of "forgot password": email a 6-digit code to the address.
     *
     * Always answers the same way so the endpoint cannot be used to discover
     * registered emails, and never returns the code itself — the only way to
     * continue is to read the mailbox.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $user = User::where('email', $email)->first();

        if ($user) {
            $code = $this->codes->issue(AuthCodePurpose::PasswordReset, $email);
            $minutes = AuthCodePurpose::PasswordReset->minutes();
            $link = $this->frontend('/reset-password?email='.urlencode($email).'&code='.$code);

            $this->mail($email, new PasswordResetCodeMail($user, $code, $link, $minutes));
            $this->noteCodeInLog(AuthCodePurpose::PasswordReset, $email, $code);
            activity()->asUser($user->id)->action('auth.password_reset_requested')
                ->describe('Requested a password reset code')->on($user);
        }

        return response()->json([
            'message' => 'If an account exists for that email, we have sent a 6-digit reset code. It expires in '
                .AuthCodePurpose::PasswordReset->minutes().' minutes.',
        ]);
    }

    /**
     * Step 2 of "forgot password": the code proves the mailbox is yours, so a
     * new password can be set. Every other device is signed out and the owner
     * is told by email that the password changed.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $user = User::where('email', $email)->first();
        $status = $user
            ? $this->codes->consume(AuthCodePurpose::PasswordReset, $email, $request->validated('code'))
            : AuthCodeService::MISSING;

        if ($status !== AuthCodeService::OK) {
            if ($user) {
                activity()->asUser($user->id)->action('auth.password_reset_failed')
                    ->describe('Entered an incorrect password reset code')->with(['status' => $status]);
            }

            throw ValidationException::withMessages([
                'code' => $this->codes->message($status, AuthCodePurpose::PasswordReset),
            ]);
        }

        $user->update(['password' => $request->validated('password')]);
        $user->tokens()->delete();

        activity()->asUser($user->id)->action('auth.password_reset')->describe('Reset the account password')->on($user);
        $this->mail($email, new PasswordChangedMail($user, now()->format('j M Y, H:i'), $request->ip()));

        return response()->json(['message' => 'Your password has been reset. Please sign in with the new password.']);
    }

    /**
     * Confirm an email address with the code sent at registration. Public, so
     * the link in the email works on any device, and it signs nobody in by
     * itself — the code only proves the address.
     */
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $user = User::where('email', $email)->first();

        if ($user && $user->email_verified_at) {
            return response()->json(['message' => 'That email address is already confirmed.', 'already_verified' => true]);
        }

        $status = $user
            ? $this->codes->consume(AuthCodePurpose::EmailVerification, $email, $request->validated('code'))
            : AuthCodeService::MISSING;

        if ($status !== AuthCodeService::OK) {
            throw ValidationException::withMessages([
                'code' => $this->codes->message($status, AuthCodePurpose::EmailVerification),
            ]);
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        activity()->asUser($user->id)->action('auth.email_verified')->describe('Confirmed the email address')->on($user);
        $this->notifications->send($user, ModuleKey::Platform, 'Email address confirmed',
            'Deadline reminders and weekly summaries will now reach '.$user->email.'.',
            NotificationType::Success, '/settings', 'envelope-check');
        $this->firebase->mirrorProfile($user);

        return response()->json([
            'message' => 'Thanks — your email address is confirmed.',
            'user' => UserResource::make($user->load('settings')),
        ]);
    }

    /** Send the verification code again (same generic answer for any address). */
    public function resendVerification(ResendVerificationRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if ($user && ! $user->email_verified_at) {
            $this->sendVerificationCode($user);
        }

        return response()->json([
            'message' => 'If that address needs confirming, a new code is on its way.',
        ]);
    }

    /** Issue + email a verification code for a freshly registered account. */
    private function sendVerificationCode(User $user): void
    {
        $code = $this->codes->issue(AuthCodePurpose::EmailVerification, $user->email);
        $link = $this->frontend('/verify-email?email='.urlencode($user->email).'&code='.$code);

        $this->mail($user->email, new EmailVerificationCodeMail($user, $code, $link));
        $this->noteCodeInLog(AuthCodePurpose::EmailVerification, $user->email, $code);
    }

    /**
     * Send mail without letting a mail problem break the request: a student
     * whose SMTP server is down still gets their account, and the failure is
     * written to the log instead of a 500.
     */
    private function mail(string $email, Mailable $mailable): void
    {
        try {
            Mail::to($email)->send($mailable);
        } catch (\Throwable $e) {
            logger()->error('AcadeAlert could not send '.$mailable::class.' to '.$email.': '.$e->getMessage());
        }
    }

    /**
     * With MAIL_MAILER=log there is no mailbox to open, so the code is written
     * to the log next to the message itself — `php artisan acadealert:last-code`
     * prints it. With a real mailer configured nothing extra is logged.
     */
    private function noteCodeInLog(AuthCodePurpose $purpose, string $email, string $code): void
    {
        if (config('mail.default') === 'log') {
            logger()->info("AcadeAlert {$purpose->value} code for {$email}: {$code}");
        }
    }

    private function frontend(string $path): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }

    /** @return array<string, mixed> */
    private function tokenPayload(User $user, Request $request): array
    {
        $minutes = config('sanctum.expiration');
        $expiresAt = $minutes ? now()->addMinutes((int) ($request->boolean('remember') ? $minutes * 4 : $minutes)) : null;
        $token = $user->createToken($this->deviceName($request), ['*'], $expiresAt);

        return [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt?->toIso8601String(),
            'user' => UserResource::make($user->load('settings')),
        ];
    }

    private function deviceName(Request $request): string
    {
        if ($name = $request->input('device_name')) {
            return Str::limit($name, 100, '');
        }
        $agent = (string) $request->userAgent();
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser',
        };
        $os = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'device',
        };

        return "{$browser} on {$os}";
    }
}
