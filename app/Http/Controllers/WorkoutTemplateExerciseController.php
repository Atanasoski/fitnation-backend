<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkoutTemplateExerciseRequest;
use App\Http\Requests\UpdateWorkoutTemplateExerciseRequest;
use App\Models\Partner;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkoutTemplateExerciseController extends Controller
{
    /**
     * Store a newly created exercise in the workout template.
     */
    public function store(StoreWorkoutTemplateExerciseRequest $request, WorkoutTemplate $workoutTemplate): RedirectResponse
    {
        // Get the highest order value and increment
        $order = $request->order ?? ($workoutTemplate->workoutTemplateExercises()->max('order') ?? -1) + 1;

        WorkoutTemplateExercise::create([
            'workout_template_id' => $workoutTemplate->id,
            'exercise_id' => $request->exercise_id,
            'order' => $order,
            'target_sets' => $request->target_sets ?? 3,
            'min_target_reps' => $request->min_target_reps ?? 8,
            'max_target_reps' => $request->max_target_reps ?? 12,
            'target_weight' => $request->target_weight ?? 0,
            'rest_seconds' => $request->rest_seconds ?? 120,
        ]);

        return redirect()->route('workouts.show', $workoutTemplate)
            ->with('success', 'Exercise added successfully!');
    }

    /**
     * Show the form for editing the specified exercise in the workout template.
     */
    public function edit(Request $request, WorkoutTemplate $workoutTemplate, WorkoutTemplateExercise $workoutTemplateExercise): View
    {
        $workoutTemplate->load('plan.user');
        if ($workoutTemplateExercise->workout_template_id !== $workoutTemplate->id) {
            abort(403, 'Unauthorized.');
        }

        $partner = Partner::with('identity')->findOrFail($request->user()->partner_id);
        $isLibrary = $workoutTemplate->plan->user_id === null;
        $user = $isLibrary ? null : $workoutTemplate->plan->user;

        $workoutTemplateExercise->load('exercise');

        $view = $isLibrary ? 'workout-template-exercises.edit' : 'workout-template-exercises.users.edit';

        return view($view, compact('workoutTemplate', 'workoutTemplateExercise', 'partner', 'user'));
    }

    /**
     * Update the specified exercise in the workout template.
     */
    public function update(UpdateWorkoutTemplateExerciseRequest $request, WorkoutTemplate $workoutTemplate, WorkoutTemplateExercise $workoutTemplateExercise): RedirectResponse
    {
        if ($workoutTemplateExercise->workout_template_id !== $workoutTemplate->id) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validated();
        $workoutTemplateExercise->update([
            'order' => $validated['order'] ?? $workoutTemplateExercise->order,
            'target_sets' => $validated['target_sets'] ?? 3,
            'min_target_reps' => $validated['min_target_reps'] ?? 8,
            'max_target_reps' => $validated['max_target_reps'] ?? 12,
            'target_weight' => $validated['target_weight'] ?? 0,
            'rest_seconds' => $validated['rest_seconds'] ?? 120,
        ]);

        return redirect()->route('workouts.show', $workoutTemplate)
            ->with('success', 'Exercise updated successfully!');
    }

    /**
     * Remove the specified exercise from the workout template.
     */
    public function destroy(Request $request, WorkoutTemplate $workoutTemplate, WorkoutTemplateExercise $workoutTemplateExercise): RedirectResponse
    {
        if ($workoutTemplateExercise->workout_template_id !== $workoutTemplate->id) {
            abort(403, 'Unauthorized.');
        }

        $workoutTemplateExercise->delete();

        return redirect()->route('workouts.show', $workoutTemplate)
            ->with('success', 'Exercise removed successfully!');
    }
}
