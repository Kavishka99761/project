<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Enums\ModuleKey;
use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ForgotPasswordRequest;
use App\Http\Requests\Platform\LoginRequest;
use App\Http\Requests\Platform\RegisterRequest;
use App\Http\Requests\Platform\ResetPasswordRequest;
use App\Http\Resources\Platform\UserResource;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Firebase\FirebaseService;
use App\Services\Platform\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Common Platform Layer — authentication: register, login, logout and
 * password recovery. Sanctum personal access tokens (one per device) with an
 * expiry; failed logins are throttled and recorded in the audit trail.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly FirebaseService $firebase,
        private readonly NotificationService $notifications,
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
        $this->notifications->send($user, ModuleKey::Platform, 'Welcome to EDU-SMART, '.Str::before($user->name, ' ').'!',
            'Start by adding your modules, then upload lecture notes, academic documents and assignments.',
            NotificationType::Success, '/modules', 'stars');
        $this->firebase->mirrorProfile($user);

        return response()->json($this->tokenPayload($user, $request), 201);
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

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        activity()->asUser($user->id)->action('auth.login')->describe('Signed in from '.$this->deviceName($request))->on($user);
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
     * Always answers the same way so the endpoint cannot be used to discover
     * registered emails. The token is emailed (logged locally with
     * MAIL_MAILER=log) and also returned while APP_DEBUG is on for demos.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $payload = ['message' => 'If an account exists for that email, a reset link has been sent.'];
        $user = User::where('email', $email)->first();

        if ($user) {
            $token = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(['email' => $email], ['token' => Hash::make($token), 'created_at' => now()]);
            $link = rtrim(config('app.frontend_url'), '/').'/reset-password?token='.$token.'&email='.urlencode($email);
            logger()->info("EDU-SMART password reset link for {$email}: {$link}");
            activity()->asUser($user->id)->action('auth.password_reset_requested')->describe('Requested a password reset link');

            if (config('app.debug')) {
                $payload['dev_reset_link'] = $link;
            }
        }

        return response()->json($payload);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $row = DB::table('password_reset_tokens')->where('email', $request->validated('email'))->first();

        if (! $row || ! Hash::check($request->validated('token'), $row->token)) {
            throw ValidationException::withMessages(['token' => 'This password reset link is invalid.']);
        }
        if (now()->diffInMinutes($row->created_at, true) > 60) {
            DB::table('password_reset_tokens')->where('email', $row->email)->delete();
            throw ValidationException::withMessages(['token' => 'This reset link has expired. Please request a new one.']);
        }

        $user = User::where('email', $row->email)->firstOrFail();
        $user->update(['password' => $request->validated('password')]);
        $user->tokens()->delete();
        DB::table('password_reset_tokens')->where('email', $row->email)->delete();

        activity()->asUser($user->id)->action('auth.password_reset')->describe('Reset the account password')->on($user);

        return response()->json(['message' => 'Your password has been reset. Please sign in with the new password.']);
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
