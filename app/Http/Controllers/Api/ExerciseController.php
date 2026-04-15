<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExerciseHistoryRequest;
use App\Http\Requests\StoreExerciseRequest;
use App\Http\Requests\UpdateExerciseRequest;
use App\Http\Resources\Api\ExerciseHistoryResource;
use App\Http\Resources\Api\ExerciseResource;
use App\Models\Exercise;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExerciseController extends Controller
{
    /**
     * Display a listing of exercises.
     */
    public function index(): AnonymousResourceCollection
    {
        $exercises = Exercise::with('category', 'muscleGroups', 'partners', 'angle', 'movementPattern', 'targetRegion', 'equipmentType')
            ->forPartner(auth()->user()?->partner)
            ->leftJoin('equipment_types', 'workout_exercises.equipment_type_id', '=', 'equipment_types.id')
            ->orderBy('equipment_types.display_order')
            // ->latest('workout_exercises.created_at')
            ->select('workout_exercises.*')
            ->get();

        return ExerciseResource::collection($exercises);
    }

    /**
     * Store a newly created exercise in storage.
     */
    public function store(StoreExerciseRequest $request): JsonResponse
    {
        $exercise = Exercise::create([
            'name' => $request->name,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'image' => $request->image,
            'default_rest_sec' => $request->default_rest_sec ?? 90,
        ]);

        return response()->json([
            'message' => 'Exercise created successfully',
            'data' => new ExerciseResource($exercise->load(['category', 'partners'])),
        ], 201);
    }

    /**
     * Display the specified exercise.
     */
    public function show(Exercise $exercise): JsonResponse
    {
        $exercise->load(['category', 'partners']);

        return response()->json([
            'data' => new ExerciseResource($exercise),
        ]);
    }

    /**
     * Update the specified exercise in storage.
     */
    public function update(UpdateExerciseRequest $request, Exercise $exercise): JsonResponse
    {
        $exercise->update([
            'name' => $request->name,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'image' => $request->image,
            'default_rest_sec' => $request->default_rest_sec,
        ]);

        return response()->json([
            'message' => 'Exercise updated successfully',
            'data' => new ExerciseResource($exercise->load(['category', 'partners'])),
        ]);
    }

    /**
     * Remove the specified exercise from storage.
     */
    public function destroy(Exercise $exercise): JsonResponse
    {
        $exercise->delete();

        return response()->json([
            'message' => 'Exercise deleted successfully',
        ]);
    }

    /**
     * Get exercise performance history.
     */
    public function history(ExerciseHistoryRequest $request, Exercise $exercise): JsonResponse
    {
        $userId = Auth::id();

        // Build base query to get completed sessions with set logs for this exercise
        $query = DB::table('workout_sessions as ws')
            ->join('workout_session_set_logs as sl', 'ws.id', '=', 'sl.workout_session_id')
            ->where('ws.user_id', $userId)
            // ->whereNotNull('ws.completed_at')
            ->where('sl.exercise_id', $exercise->id)
            ->select([
                'ws.id as session_id',
                'ws.completed_at',
                DB::raw('MAX(sl.weight) as weight'),
                DB::raw('MAX(sl.reps) as best_set_reps'),
                DB::raw('SUM(sl.reps) as reps'),
                DB::raw('SUM(sl.weight * sl.reps) as volume'),
                DB::raw('COUNT(sl.id) as sets'),
            ])
            ->groupBy('ws.id', 'ws.completed_at');

        // Apply date range filters if provided
        if ($request->filled('start_date')) {
            $startDate = Carbon::createFromFormat('Y-m-d', $request->start_date)->startOfDay();
            $query->where('ws.completed_at', '>=', $startDate);
        }

        if ($request->filled('end_date')) {
            $endDate = Carbon::createFromFormat('Y-m-d', $request->end_date)->endOfDay();
            $query->where('ws.completed_at', '<=', $endDate);
        }

        // Order by date ascending
        $query->orderBy('ws.created_at', 'asc');

        // Apply limit if provided
        if ($request->filled('limit')) {
            $query->limit($request->limit);
        }

        $sessions = $query->get();

        // Transform to performance data points
        $performanceData = $sessions->map(function ($session) {
            return [
                'date' => Carbon::parse($session->completed_at)->format('Y-m-d'),
                'session_id' => $session->session_id,
                'weight' => (float) $session->weight,
                'best_set_reps' => (int) $session->best_set_reps,
                'reps' => (int) $session->reps,
                'volume' => (float) $session->volume,
                'sets' => (int) $session->sets,
            ];
        })->values()->toArray();

        // Calculate stats
        $stats = $this->calculateStats($performanceData);

        // Prepare response data
        $responseData = [
            'exercise_id' => $exercise->id,
            'exercise_name' => $exercise->name,
            'stats' => $stats,
            'performance_data' => $performanceData,
        ];

        return response()->json([
            'data' => new ExerciseHistoryResource($responseData),
        ]);
    }

    /**
     * Calculate exercise history statistics.
     *
     * @param  array<int, array<string, mixed>>  $performanceData
     * @return array<string, mixed>
     */
    private function calculateStats(array $performanceData): array
    {
        if (empty($performanceData)) {
            return [
                'current_weight' => 0,
                'best_weight' => 0,
                'current_best_set_reps' => 0,
                'best_set_reps' => 0,
                'progress_percentage' => 0,
                'total_sessions' => 0,
                'first_session_date' => null,
                'last_session_date' => null,
            ];
        }

        $totalSessions = count($performanceData);
        $firstSession = $performanceData[0];
        $lastSession = $performanceData[$totalSessions - 1];

        $currentWeight = (float) $lastSession['weight'];
        $firstWeight = (float) $firstSession['weight'];
        $bestWeight = max(array_column($performanceData, 'weight'));

        // Best reps stats (useful for bodyweight exercises)
        $currentBestSetReps = (int) $lastSession['best_set_reps'];
        $bestSetReps = max(array_column($performanceData, 'best_set_reps'));

        return [
            'current_weight' => $currentWeight,
            'best_weight' => (float) $bestWeight,
            'current_best_set_reps' => $currentBestSetReps,
            'best_set_reps' => $bestSetReps,
            'total_sessions' => $totalSessions,
            'first_session_date' => $firstSession['date'],
            'last_session_date' => $lastSession['date'],
        ];
    }
}
