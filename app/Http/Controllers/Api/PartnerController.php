<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\NearbyPartnerResource;
use App\Http\Resources\Api\PartnerResource;
use App\Models\GymLocation;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartnerController extends Controller
{
    /**
     * List all active partners (public — used by mobile registration dropdown).
     */
    public function activeList(): AnonymousResourceCollection
    {
        $partners = Partner::with('identity')->where('is_active', true)->orderBy('name')->get();

        return PartnerResource::collection($partners);
    }

    /**
     * The Partners whose Gym Location covers the caller's position, nearest
     * first, for the Join Prompt (issue #88). Only active Partners with the
     * Join Prompt enabled match. Ineligible callers — anyone not on the House
     * Partner, with an active subscription, or on staff — get an empty list,
     * decided here and never trusted to the client.
     *
     * The coordinates are used for this lookup only: never persisted, never
     * logged.
     */
    public function nearby(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $user = $request->user();

        $eligible = $user->partner?->isHouse()
            // The raw paid-through check, deliberately not entitlements():
            // the dark-deploy flag (subscriptions.enforced) grants everyone
            // every entitlement and must not silence the Join Prompt.
            && ! $user->subscription?->isActive()
            && ! $user->isStaff();

        if (! $eligible) {
            return NearbyPartnerResource::collection(collect());
        }

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];

        // The point-in-radius check runs in PHP over the Gym Locations of
        // geo-enabled Partners rather than in SQL: the candidate set is tiny
        // (Partners are enabled one at a time) and this keeps the matching
        // portable across MySQL and SQLite.
        $partners = GymLocation::query()
            ->whereHas('partner', fn ($query) => $query
                ->where('is_active', true)
                ->where('geo_prompt_enabled', true))
            ->with('partner.identity')
            ->get()
            ->filter(fn (GymLocation $location) => $location->covers($latitude, $longitude))
            // A Partner with several covering Gym Locations counts once,
            // ranked by its nearest one.
            ->groupBy('partner_id')
            ->map(fn ($locations) => [
                'partner' => $locations->first()->partner,
                'distance' => $locations->min(
                    fn (GymLocation $location) => $location->distanceToMeters($latitude, $longitude),
                ),
            ])
            ->sortBy('distance')
            ->values()
            ->pluck('partner');

        return NearbyPartnerResource::collection($partners);
    }

    public function branding(Partner $partner): JsonResponse
    {

        $partner->load('identity');

        return response()->json([
            'data' => new PartnerResource($partner),
        ], 200);
    }
}
