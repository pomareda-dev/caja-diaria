<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserProfilePhotoController extends Controller
{
    /**
     * Store a new profile photo for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $file = $validated['photo'];

        // Delete previous avatar if exists
        $currentAvatarPath = $user->settings['avatar_path'] ?? null;
        if ($currentAvatarPath) {
            Storage::disk('public')->delete($currentAvatarPath);
        }

        // Store the new file
        $extension = $file->getClientOriginalExtension();
        $path = $file->storeAs('avatars', "{$user->id}.{$extension}", 'public');

        // Update user settings
        $settings = array_merge($user->settings ?? [], ['avatar_path' => $path]);
        $user->settings = $settings;
        $user->save();

        return response()->json([
            'avatar_path' => $path,
            'avatar_url' => Storage::disk('public')->url($path),
        ]);
    }
}
