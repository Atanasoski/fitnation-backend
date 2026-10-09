<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Services\Subscription\SubscriptionSync;
use App\Services\Subscription\SubscriptionSyncFailed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Re-read the user's subscription from RevenueCat and answer with the
     * same payload as GET /user. Not behind the subscription gate: the user
     * calling it may be blocked only because a webhook is late.
     */
    public function sync(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            SubscriptionSync::run($user);
        } catch (SubscriptionSyncFailed $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason], $e->status);
        }

        return response()->json([
            'user' => new UserResource($user->load(UserResource::RELATIONS)),
        ]);
    }
}
