<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Notifications\WeeklySummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    /**
     * Flip the user's Push Switch and, when sent, the Notification Settings
     * (CONTEXT.md) that live beside it. The switch stays required — it is the
     * contract the app already speaks; notification_settings is additive.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'push_enabled' => ['required', 'boolean'],
            'notification_settings' => ['sometimes', 'array:'.WeeklySummary::SETTING],
            'notification_settings.'.WeeklySummary::SETTING => ['sometimes', 'boolean'],
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
