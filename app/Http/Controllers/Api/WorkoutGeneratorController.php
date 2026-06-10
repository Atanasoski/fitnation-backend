<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateWorkoutSessionRequest;
use App\Http\Requests\RegenerateWorkoutSessionRequest;
use App\Http\Resources\Api\GeneratedWorkoutSessionResource;
use App\Models\WorkoutSession;
use App\Services\WorkoutGenerator\WorkoutGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WorkoutGeneratorController extends Controller
{
    public function __construct(
        private WorkoutGenerationService $workoutGenerationService
    ) {}

    /**
     * Generate a new draft workout session
     * User can modify exercises and then confirm to start the workout
     */
    public function generate(GenerateWorkoutSessionRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $preferences = [
                'target_regions' => $request->input('target_regions'),
                'equipment_types' => $request->input('equipment_types'),
                'movement_patterns' => $request->input('movement_patterns'),
                'angles' => $request->input('angles'),
                'training_styles' => $request->input('training_styles'),
                'duration_minutes' => $request->input('duration_minutes'),
                'difficulty' => $request->input('difficulty'),
            ];

            // Remove null and empty array values
            $preferences = array_filter(
                $preferences,
                fn ($value) => $value !== null && $value !== []
            );

            $session = $this->workoutGenerationService->generate($user, $preferences);

            return response()->json([
                'data' => new GeneratedWorkoutSessionResource($session),
                'message' => 'Draft workout session created successfully',
            ], 201);
        } catch (\Exception $e) {
            return $this->handleGenerationError($e);
        }
    }

    /**
     * Confirm a draft workout session - sets status to active and starts the workout
     */
    public function confirm(WorkoutSession $session): JsonResponse
    {
        try {
            $this->authorize('confirm', $session);

            $confirmedSession = $this->workoutGenerationService->confirmSession($session);

            return response()->json([
                'data' => new GeneratedWorkoutSessionResource($confirmedSession),
                'message' => 'Workout session confirmed and started successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Workout session confirmation failed', [
                'user_id' => Auth::id(),
                'session_id' => $session->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $message = $e->getMessage();

            if (str_contains($message, 'Only draft sessions')) {
                return response()->json([
                    'message' => $message,
                ], 422);
            }

            return response()->json([
                'message' => 'Failed to confirm workout session. Please try again.',
            ], 500);
        }
    }

    /**
     * Regenerate a draft workout session - cancel current and create new one
     */
    public function regenerate(RegenerateWorkoutSessionRequest $request, WorkoutSession $session): JsonResponse
    {
        try {
            $this->authorize('regenerate', $session);

            $preferences = [
                'target_regions' => $request->input('target_regions'),
                'equipment_types' => $request->input('equipment_types'),
                'movement_patterns' => $request->input('movement_patterns'),
                'angles' => $request->input('angles'),
                'training_styles' => $request->input('training_styles'),
                'duration_minutes' => $request->input('duration_minutes'),
                'difficulty' => $request->input('difficulty'),
            ];

            // Remove null and empty array values
            $preferences = array_filter(
                $preferences,
                fn ($value) => $value !== null && $value !== []
            );

            $newSession = $this->workoutGenerationService->regenerateSession($session, $preferences);

            return response()->json([
                'data' => new GeneratedWorkoutSessionResource($newSession),
                'message' => 'New workout session generated successfully',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Workout session regeneration failed', [
                'user_id' => Auth::id(),
                'session_id' => $session->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $message = $e->getMessage();

            if (str_contains($message, 'Only draft sessions')) {
                return response()->json([
                    'message' => $message,
                ], 422);
            }

            return response()->json([
                'message' => 'Failed to regenerate workout session. Please try again.',
            ], 500);
        }
    }

    /**
     * Handle generation errors with user-friendly messages
     */
    private function handleGenerationError(\Exception $e): JsonResponse
    {
        Log::error('Workout generation failed', [
            'user_id' => Auth::id(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $message = $e->getMessage();

        // Provide user-friendly error messages
        if (str_contains($message, 'profile is required')) {
            return response()->json([
                'message' => 'Please complete your profile before generating workouts.',
            ], 422);
        }

        if (str_contains($message, 'Fitness goal is required')) {
            return response()->json([
                'message' => 'Please set your fitness goal in your profile.',
            ], 422);
        }

        if (str_contains($message, 'Training experience is required')) {
            return response()->json([
                'message' => 'Please set your training experience level in your profile.',
            ], 422);
        }

        if (str_contains($message, 'No exercises')) {
            return response()->json([
                'message' => $message,
            ], 422);
        }

        return response()->json([
            'message' => 'Failed to generate workout. Please try again later.',
        ], 500);
    }
}
