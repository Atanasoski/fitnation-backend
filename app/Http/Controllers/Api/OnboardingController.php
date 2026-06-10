<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CompleteOnboardingRequest;
use App\Http\Resources\Api\CustomPlanResource;
use App\Services\WelcomePlanGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class OnboardingController extends Controller
{
    public function __construct(
        private WelcomePlanGenerationService $planGenerationService
    ) {}

    /**
     * Complete onboarding and generate welcome plan
     */
    public function complete(CompleteOnboardingRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            $preferences = array_filter([
                'equipment_types' => $request->input('equipment_types'),
                'movement_patterns' => $request->input('movement_patterns'),
                'angles' => $request->input('angles'),
                'training_styles' => $request->input('training_styles'),
            ], fn ($v) => $v !== null);

            $plan = $this->planGenerationService->generateWelcomePlan(
                $user,
                $request->input('plan_name'),
                $preferences
            );

            return response()->json([
                'message' => 'Welcome plan created successfully',
                'data' => new CustomPlanResource($plan->load(['workoutTemplates.exercises.category', 'workoutTemplates.exercises.muscleGroups'])),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Onboarding completion failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $message = $e->getMessage();

            // Provide user-friendly error messages
            if (str_contains($message, 'already been completed')) {
                return response()->json([
                    'message' => 'Onboarding has already been completed',
                ], 409);
            }

            if (str_contains($message, 'is required')) {
                return response()->json([
                    'message' => $message,
                ], 422);
            }

            return response()->json([
                'message' => 'Failed to complete onboarding. Please try again later.',
            ], 500);
        }
    }
}
