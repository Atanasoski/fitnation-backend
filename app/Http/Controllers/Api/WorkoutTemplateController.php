<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkoutTemplateRequest;
use App\Http\Requests\UpdateWorkoutTemplateRequest;
use App\Http\Resources\Api\WorkoutTemplateResource;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkoutTemplateController extends Controller
{
    /**
     * Display a listing of workout templates.
     */
    public function index(): AnonymousResourceCollection
    {
        $templates = WorkoutTemplate::whereHas('plan', function ($query) {
            $query->where('user_id', auth()->id());
        })
            ->with(['exercises.category', 'exercises.muscleGroups', 'exercises.partners'])
            ->orderBy('name')
            ->get();

        return WorkoutTemplateResource::collection($templates);
    }

    /**
     * Store a newly created workout template in storage.
     */
    public function store(StoreWorkoutTemplateRequest $request): JsonResponse
    {
        $template = WorkoutTemplate::create([
            'plan_id' => $request->plan_id,
            'name' => $request->name,
            'description' => $request->description,
            'day_of_week' => $request->day_of_week,
        ]);

        $template->load(['exercises.category', 'exercises.muscleGroups', 'exercises.partners']);

        return response()->json([
            'message' => 'Workout template created successfully',
            'data' => new WorkoutTemplateResource($template),
        ], 201);
    }

    /**
     * Display the specified workout template.
     */
    public function show(WorkoutTemplate $workoutTemplate): JsonResponse
    {
        // Authorization check
        $workoutTemplate->load('plan');
        if ($workoutTemplate->plan->user_id !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $workoutTemplate->load(['exercises.category', 'exercises.muscleGroups', 'exercises.partners']);

        return response()->json([
            'data' => new WorkoutTemplateResource($workoutTemplate),
        ]);
    }

    /**
     * Update the specified workout template in storage.
     */
    public function update(UpdateWorkoutTemplateRequest $request, WorkoutTemplate $workoutTemplate): JsonResponse
    {
        // Authorization check
        $workoutTemplate->load('plan');
        if ($workoutTemplate->plan->user_id !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $workoutTemplate->update($request->validated());

        return response()->json([
            'message' => 'Workout template updated successfully',
            'data' => new WorkoutTemplateResource($workoutTemplate->load(['exercises.category', 'exercises.partners'])),
        ]);
    }

    /**
     * Remove the specified workout template from storage.
     */
    public function destroy(WorkoutTemplate $workoutTemplate): JsonResponse
    {
        // Authorization check
        $workoutTemplate->load('plan');
        if ($workoutTemplate->plan->user_id !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $workoutTemplate->delete();

        return response()->json([
            'message' => 'Workout template deleted successfully',
        ]);
    }

    /**
     * Add exercise to workout template.
     */
    public function addExercise(Request $request, WorkoutTemplate $workoutTemplate): JsonResponse
    {
        // Authorization check
        $workoutTemplate->load('plan');
        if ($workoutTemplate->plan->user_id !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'exercise_id' => 'required|exists:workout_exercises,id',
        ]);

        // Get the highest order value and increment
        $maxOrder = $workoutTemplate->workoutTemplateExercises()->max('order') ?? -1;

        WorkoutTemplateExercise::create([
            'workout_template_id' => $workoutTemplate->id,
            'exercise_id' => $validated['exercise_id'],
            'order' => $maxOrder + 1,
        ]);

        $workoutTemplate->load(['exercises.category', 'exercises.muscleGroups', 'exercises.partners']);

        return response()->json([
            'message' => 'Exercise added successfully',
            'data' => new WorkoutTemplateResource($workoutTemplate),
        ], 201);
    }

    /**
     * Remove exercise from workout template.
     */
    public function removeExercise(WorkoutTemplate $workoutTemplate, WorkoutTemplateExercise $exercise): JsonResponse
    {
        // Authorization check
        $workoutTemplate->load('plan');
        if ($workoutTemplate->plan->user_id !== auth()->id() || $exercise->workout_template_id !== $workoutTemplate->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $exercise->delete();

        $workoutTemplate->load(['exercises.category', 'exercises.muscleGroups', 'exercises.partners']);

        return response()->json([
            'message' => 'Exercise removed successfully',
            'data' => new WorkoutTemplateResource($workoutTemplate),
        ]);
    }

    /**
     * Update exercise in workout template.
     */
    public function updateExercise(Request $request, WorkoutTemplate $workoutTemplate, WorkoutTemplateExercise $exercise): JsonResponse
    {
        // Authorization check
        $workoutTemplate->load('plan');
        if ($workoutTemplate->plan->user_id !== auth()->id() || $exercise->workout_template_id !== $workoutTemplate->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'target_sets' => 'nullable|integer|min:1',
            'min_target_reps' => 'nullable|integer|min:1',
            'max_target_reps' => 'nullable|integer|min:1|gte:min_target_reps',
            'target_weight' => 'nullable|numeric|min:0',
            'rest_seconds' => 'nullable|integer|min:0',
        ]);

        $exercise->update($validated);

        $workoutTemplate->load(['exercises.category', 'exercises.muscleGroups', 'exercises.partners']);

        return response()->json([
            'message' => 'Exercise updated successfully',
            'data' => new WorkoutTemplateResource($workoutTemplate),
        ]);
    }

    /**
     * Update exercise order in workout template.
     */
    public function updateOrder(Request $request, WorkoutTemplate $workoutTemplate): JsonResponse
    {
        // Authorization check
        $workoutTemplate->load('plan');
        if ($workoutTemplate->plan->user_id !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|integer',
        ]);

        // Verify all IDs belong to this template
        $templateExerciseIds = $workoutTemplate->workoutTemplateExercises->pluck('id')->toArray();
        $requestIds = $validated['order'];

        $invalidIds = array_diff($requestIds, $templateExerciseIds);
        if (! empty($invalidIds)) {
            return response()->json([
                'message' => 'Invalid exercise IDs provided',
                'errors' => ['order' => ['Some exercise IDs do not belong to this template']],
            ], 422);
        }

        // Update the order
        foreach ($validated['order'] as $index => $id) {
            WorkoutTemplateExercise::where('id', $id)
                ->where('workout_template_id', $workoutTemplate->id)
                ->update(['order' => $index]);
        }

        $workoutTemplate->load(['exercises.category', 'exercises.muscleGroups', 'exercises.partners']);

        return response()->json([
            'message' => 'Order updated successfully',
            'data' => new WorkoutTemplateResource($workoutTemplate),
        ]);
    }
}
