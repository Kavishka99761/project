<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Common Platform Layer — authentication (register / login / logout / me),
 * password recovery, password change, and profile/avatar management.
 * Uses Laravel Sanctum personal access tokens for both the web and mobile apps.
 */
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'program'  => ['nullable', 'string', 'max:160'],
        ]);

        $user = User::create($data);
        $token = $user->createToken('edusmart')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('edusmart')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('modules'));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                 => ['sometimes', 'string', 'max:120'],
            'program'              => ['nullable', 'string', 'max:160'],
            'academic_year'        => ['nullable', 'string', 'max:80'],
            'phone'                => ['nullable', 'string', 'max:30'],
            'bio'                  => ['nullable', 'string', 'max:500'],
            'dark_mode'            => ['sometimes', 'boolean'],
            'daily_target_minutes' => ['sometimes', 'integer', 'min:15', 'max:1440'],
        ]);

        $user = $request->user();
        $user->update($data);

        return response()->json($user);
    }

    /**
     * POST /api/me/avatar — upload/replace the profile photo (stored on the
     * `public` disk, served at `avatar_url`). Deletes the previous file, if any.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:4096'], // 4 MB
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars/'.$user->id, 'public');
        $user->update(['avatar_path' => $path]);

        return response()->json($user->fresh());
    }

    /** DELETE /api/me/avatar — remove the current profile photo. */
    public function removeAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return response()->json($user->fresh());
    }

    /**
     * POST /api/me/password — change password while signed in. Revokes every
     * other access token so other sessions must sign back in with the new one.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'          => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Your current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        // Keep the session that just changed the password signed in; drop the rest.
        $currentTokenId = $request->user()->currentAccessToken()->id ?? null;
        $user->tokens()->when($currentTokenId, fn ($q) => $q->where('id', '!=', $currentTokenId))->delete();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    /**
     * POST /api/forgot-password — public. Issues a reset token valid for 60
     * minutes. In production this would only be emailed (MAIL_MAILER); to keep
     * the flow testable without an SMTP server configured, the raw token is
     * also returned in the response when APP_DEBUG=true (local/demo only).
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $email = $request->string('email')->toString();
        $user = User::where('email', $email)->first();

        // Always respond the same way whether or not the email exists, so the
        // endpoint can't be used to enumerate registered accounts.
        $generic = ['message' => 'If an account exists for that email, a reset link has been sent.'];

        if (! $user) {
            return response()->json($generic);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        // TODO(production): dispatch a real notification/mailable here once an
        // SMTP mailer is configured — logged for now via MAIL_MAILER=log.
        logger()->info("EDU-SMART password reset requested for {$email}. Token: {$token}");

        if (config('app.debug')) {
            $generic['dev_reset_token'] = $token;
            $generic['dev_note'] = 'Shown only because APP_DEBUG=true — remove in production.';
        }

        return response()->json($generic);
    }

    /** POST /api/reset-password — public. Consumes the token issued above. */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'token'    => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $row = DB::table('password_reset_tokens')->where('email', $data['email'])->first();

        if (! $row || ! Hash::check($data['token'], $row->token)) {
            throw ValidationException::withMessages(['token' => ['This reset link is invalid.']]);
        }

        if (now()->diffInMinutes($row->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
            throw ValidationException::withMessages(['token' => ['This reset link has expired. Request a new one.']]);
        }

        $user = User::where('email', $data['email'])->firstOrFail();
        $user->update(['password' => Hash::make($data['password'])]);
        $user->tokens()->delete(); // force re-login everywhere after a reset

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        return response()->json(['message' => 'Your password has been reset. Please sign in.']);
    }
}
