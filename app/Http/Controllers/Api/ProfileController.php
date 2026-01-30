<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('profile', 'roles');

        return response()->json([
            'user' => $user,
            'profile' => $user->profile,
        ]);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Update user name if provided
        if (isset($validated['name'])) {
            $user->update(['name' => $validated['name']]);
            unset($validated['name']);
        }

        // Update or create profile
        if (!empty($validated)) {
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                $validated
            );
        }

        $user->load('profile');

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
            'profile' => $user->profile,
        ]);
    }

    /**
     * Upload or update avatar.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

        $user = $request->user();

        // Delete old avatar if exists
        if ($user->profile && $user->profile->avatar_url) {
            $oldPath = str_replace('/storage/', '', $user->profile->avatar_url);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        // Store new avatar
        $path = $request->file('avatar')->store('avatars', 'public');
        $avatarUrl = '/storage/' . $path;

        // Update profile
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            ['avatar_url' => $avatarUrl]
        );

        $user->load('profile');

        return response()->json([
            'message' => 'Avatar uploaded successfully',
            'avatar_url' => $avatarUrl,
            'profile' => $user->profile,
        ]);
    }

    /**
     * Delete avatar.
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->profile && $user->profile->avatar_url) {
            $oldPath = str_replace('/storage/', '', $user->profile->avatar_url);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            $user->profile->update(['avatar_url' => null]);
        }

        return response()->json([
            'message' => 'Avatar deleted successfully',
        ]);
    }

    /**
     * Change user password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }
}
