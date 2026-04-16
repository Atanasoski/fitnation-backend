<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get authenticated user.
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
}
