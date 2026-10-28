<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicStationExerciseResource;
use App\Http\Resources\Api\PublicStationResource;
use App\Models\Station;
use Illuminate\Http\JsonResponse;

/**
 * The public, unauthenticated side of a Station: what a QR scan resolves to.
 *
 * Printed stickers are the callers here (ADR-0005), so a missing station is a
 * well-formed answer, not an error page: `gone` for a soft-deleted station,
 * `not_found` for a code that never existed. The web app renders both as the
 * graceful fallback.
 */
class PublicStationController extends Controller
{
    public function show(string $code): JsonResponse
    {
        $station = $this->liveStation($code);

        if ($station instanceof JsonResponse) {
            return $station;
        }

        $station->load([
            'partner',
            'exercises' => fn ($query) => $query->available(),
            'exercises.primaryMuscleGroups',
            'exercises.equipmentType',
            'exercises.partners',
        ]);

        return (new PublicStationResource($station))
            ->response()
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * One exercise in the station's context: full guidance, with the
     * station's partner overrides applied. An exercise that is archived or
     * not on this station's curated list is not served.
     */
    public function showExercise(string $code, int $exerciseId): JsonResponse
    {
        $station = $this->liveStation($code);

        if ($station instanceof JsonResponse) {
            return $station;
        }

        $exercise = $station->exercises()
            ->available()
            ->whereKey($exerciseId)
            ->with(['primaryMuscleGroups', 'secondaryMuscleGroups', 'equipmentType', 'partners'])
            ->first();

        if ($exercise === null) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'This exercise is not available at this station.',
            ], 404);
        }

        $station->load('partner');

        return (new PublicStationExerciseResource($exercise, $station->partner))
            ->additional(['station' => [
                'name' => $station->name,
                'short_code' => $station->short_code,
                'partner' => [
                    'name' => $station->partner->name,
                    'slug' => $station->partner->slug,
                ],
            ]])
            ->response()
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * The station a sticker's code points at, or the well-formed refusal:
     * `not_found` (404) for a code that never existed, `gone` (410) for a
     * soft-deleted station.
     */
    private function liveStation(string $code): Station|JsonResponse
    {
        $station = Station::withTrashed()->where('short_code', $code)->first();

        if ($station === null) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'No station exists for this code.',
            ], 404);
        }

        if ($station->trashed()) {
            return response()->json([
                'status' => 'gone',
                'message' => 'This station is no longer available.',
            ], 410);
        }

        return $station;
    }
}
