<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProfileUpdateRequest;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Get authenticated user's profile.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load([
                'partner',
                'profile',
                'activeSubscription' => fn ($q) => $q->with(['subscriptionPlan' => fn ($q) => $q->withTrashed()]),
            ])),
        ]);
    }

    /**
     * Update authenticated user's profile.
     */
    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Update user fields (exclude profile_photo as it needs special handling)
        $userFields = ['name', 'email'];
        $userData = array_intersect_key($validated, array_flip($userFields));
        if (! empty($userData)) {
            $user->fill($userData);
        }

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            // Delete old photo if exists
            if ($user->profile_photo && Storage::exists($user->profile_photo)) {
                Storage::delete($user->profile_photo);
            }

            // Store new photo
            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $user->profile_photo = $path;
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // Update or create user profile
        $profileFields = [
            'fitness_goal',
            'age',
            'gender',
            'height',
            'weight',
            'training_experience',
            'training_days_per_week',
            'workout_duration_minutes',
        ];
        $profileData = array_intersect_key($validated, array_flip($profileFields));

        if (! empty($profileData)) {
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => new UserResource($user->load([
                'partner',
                'profile',
                'activeSubscription' => fn ($q) => $q->with(['subscriptionPlan' => fn ($q) => $q->withTrashed()]),
            ])),
        ]);
    }

    /**
     * Delete the user's profile photo.
     */
    public function deletePhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->profile_photo && Storage::exists($user->profile_photo)) {
            Storage::delete($user->profile_photo);
        }

        $user->profile_photo = null;
        $user->save();

        return response()->json([
            'message' => 'Profile photo deleted successfully',
            'user' => new UserResource($user->load([
                'partner',
                'profile',
                'activeSubscription' => fn ($q) => $q->with(['subscriptionPlan' => fn ($q) => $q->withTrashed()]),
            ])),
        ]);
    }
}
