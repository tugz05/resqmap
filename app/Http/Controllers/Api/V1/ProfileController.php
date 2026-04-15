<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Return the authenticated user's full profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'location',
            'rescuerProfile',
            'residentProfile',
        ]);

        return response()->json(['user' => new UserResource($user)]);
    }

    /**
     * Update the authenticated user's name and/or email.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'  => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'unique:users,email,'.$user->id],
        ]);

        $user->update($validated);

        return response()->json([
            'user'    => new UserResource($user->refresh()->load('location')),
            'message' => 'Profile updated.',
        ]);
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update(['password' => $request->password]);

        return response()->json(['message' => 'Password updated.']);
    }
}
