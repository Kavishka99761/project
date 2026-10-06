<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ChangePasswordRequest;
use App\Http\Requests\Platform\UpdateProfileRequest;
use App\Http\Resources\Platform\UserResource;
use App\Services\Firebase\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Common Platform Layer — student profile, avatar, password management,
 * signed-in devices and account deletion.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly FirebaseService $firebase) {}

    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->update($request->validated());
        activity()->action('profile.updated')->describe('Updated profile information')->on($user);
        $this->firebase->mirrorProfile($user);

        return UserResource::make($user->load('settings'));
    }

    public function uploadAvatar(Request $request): UserResource
    {
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=64,min_height=64,max_width=6000,max_height=6000']]);
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $path = $request->file('avatar')->store('avatars/'.$user->id, 'public');
        $user->update(['avatar_path' => $path]);

        activity()->action('profile.avatar_updated')->describe('Changed profile photo')->on($user);
        $this->firebase->mirrorProfile($user);

        return UserResource::make($user->load('settings'));
    }

    public function removeAvatar(Request $request): UserResource
    {
        $user = $request->user();
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }
        activity()->action('profile.avatar_removed')->describe('Removed profile photo')->on($user);

        return UserResource::make($user->load('settings'));
    }

    /** Change password; every other device is signed out. */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['password' => $request->validated('password')]);
        $current = $user->currentAccessToken()?->id;
        $revoked = $user->tokens()->when($current, fn ($q) => $q->where('id', '!=', $current))->delete();

        activity()->action('auth.password_changed')->describe("Changed password and signed out {$revoked} other device(s)")->on($user);

        return response()->json(['message' => 'Password changed. Other devices have been signed out.', 'revoked' => $revoked]);
    }

    /** Signed-in devices (Sanctum tokens). */
    public function sessions(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken()?->id;

        return response()->json($request->user()->tokens()->latest('last_used_at')->get()->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'current' => $token->id === $current,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
        ]));
    }

    public function revokeSession(Request $request, int $token): JsonResponse
    {
        $deleted = $request->user()->tokens()->whereKey($token)->delete();
        abort_if($deleted === 0, 404);
        activity()->action('auth.session_revoked')->describe('Signed out another device');

        return response()->json(['message' => 'Device signed out.']);
    }

    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken()?->id;
        $count = $request->user()->tokens()->when($current, fn ($q) => $q->where('id', '!=', $current))->delete();
        activity()->action('auth.sessions_revoked')->describe("Signed out {$count} other device(s)");

        return response()->json(['message' => "Signed out {$count} other device(s).", 'revoked' => $count]);
    }

    /** Permanently delete the account and every record (SQL Server cascades). */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password:sanctum'], 'confirm' => ['required', 'in:DELETE']]);
        $user = $request->user();

        foreach (['documents', 'knowledge'] as $area) {
            Storage::disk(config('edusmart.uploads.disk'))->deleteDirectory("{$area}/{$user->id}");
        }
        Storage::disk('public')->deleteDirectory("avatars/{$user->id}");

        activity()->skip();
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Your account and all associated data have been deleted.']);
    }
}
