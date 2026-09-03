<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    /**
     * Flip the user's one global push switch.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'push_enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $user->update(['push_enabled' => $validated['push_enabled']]);

        return response()->json([
            'user' => new UserResource($user->load(['partner', 'profile'])),
        ]);
    }
}
