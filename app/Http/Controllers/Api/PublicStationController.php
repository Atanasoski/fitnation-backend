<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
}
