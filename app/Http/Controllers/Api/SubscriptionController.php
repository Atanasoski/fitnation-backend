<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SubscriptionPlanResource;
use App\Http\Resources\Api\SubscriptionResource;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends Controller
{
    public function current(Request $request): JsonResponse
    {
        $withPlan = ['subscriptionPlan' => fn ($q) => $q->withTrashed()];

        $active = $request->user()
            ->activeSubscription()
            ->with($withPlan)
            ->first();

        $upcoming = $request->user()
            ->upcomingSubscription()
            ->with($withPlan)
            ->first();

        return response()->json([
            'data' => $active !== null ? new SubscriptionResource($active) : null,
            'upcoming' => $upcoming !== null ? new SubscriptionResource($upcoming) : null,
        ]);
    }

    public function history(Request $request): AnonymousResourceCollection
    {
        $subscriptions = $request->user()
            ->subscriptions()
            ->with(['subscriptionPlan' => fn ($q) => $q->withTrashed()])
            ->orderByDesc('starts_at')
            ->get();

        return SubscriptionResource::collection($subscriptions);
    }

    public function availablePlans(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if ($user->partner_id === null) {
            return SubscriptionPlanResource::collection(collect());
        }

        $plans = SubscriptionPlan::query()
            ->where('partner_id', $user->partner_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return SubscriptionPlanResource::collection($plans);
    }
}
