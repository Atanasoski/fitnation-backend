<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\FitnessMetricsResource;
use App\Services\FitnessMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FitnessMetricsController extends Controller
{
    /**
     * Get fitness metrics for the authenticated user.
     */
    public function index(FitnessMetricsService $metrics): JsonResponse
    {
        $resource = new FitnessMetricsResource($metrics->getMetrics(Auth::user()));

        return response()->json($resource->toArray(request()));
    }
}
