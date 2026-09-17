<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Common Platform Layer — authentication (register / login / logout / me).
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
            'dark_mode'            => ['sometimes', 'boolean'],
            'daily_target_minutes' => ['sometimes', 'integer', 'min:15', 'max:1440'],
        ]);

        $user = $request->user();
        $user->update($data);

        return response()->json($user);
    }
}
