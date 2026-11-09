<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GymJoinController extends Controller
{
    /**
     * Perform a Gym Join: move the calling House Partner customer onto the
     * target Partner (GLOSSARY: Gym Join). One-way per ADR 0006.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
        ]);

        $user = $request->user();

        // One-way per ADR 0006: only House Partner customers may join a gym.
        // Anyone already on a Partner is kept there; staff reassignment in
        // the admin panel is the only way back.
        if (! $user->partner?->isHouse()) {
            abort(403, 'This account cannot join a gym. To change gyms, contact support.');
        }

        $target = Partner::query()->findOrFail($validated['partner_id']);

        if (! $target->acceptsJoins()) {
            abort(403, 'This gym is not accepting joins.');
        }

        // A Gym Join only changes which Partner the customer belongs to;
        // everything else (email verification included) stays untouched.
        $user->partner_id = $validated['partner_id'];
        $user->save();

        return response()->json([
            'message' => 'Gym joined successfully',
            'user' => new UserResource($user->load(['partner', 'profile'])),
        ]);
    }
}
