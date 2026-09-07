<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    /**
     * Flip the user's one global push switch and, when sent, the per-category
     * email preferences that live beside it. The switch stays required — it is
     * the contract the app already speaks; notification_settings is additive.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'push_enabled' => ['required', 'boolean'],
            'notification_settings' => ['sometimes', 'array:weekly_summary_email'],
            'notification_settings.weekly_summary_email' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $user->push_enabled = $validated['push_enabled'];

        foreach ($validated['notification_settings'] ?? [] as $key => $value) {
            $user->setNotificationSetting($key, $value);
        }

        $user->save();

        return response()->json([
            'user' => new UserResource($user->load(['partner', 'profile'])),
        ]);
    }
}
