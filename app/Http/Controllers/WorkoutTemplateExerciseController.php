<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkoutTemplateExerciseRequest;
use App\Http\Requests\UpdateWorkoutTemplateExerciseRequest;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use App\Rules\ExerciseThePlanOffers;
use App\Services\Plan\PlanOutline;
use App\Services\Plan\WorkoutRowOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A user's plan's workout rows, written from the plan outline; each write
 * reopens the outline. A library plan (no user) never gets here:
 * EnsureUserPlan 404s its routes.
 */
class WorkoutTemplateExerciseController extends Controller
{
    /**
     * Store a newly created exercise in the workout template.
     */
    public function store(StoreWorkoutTemplateExerciseRequest $request, WorkoutTemplate $workoutTemplate): RedirectResponse
    {
        $row = WorkoutTemplateExercise::create([
            'workout_template_id' => $workoutTemplate->id,
            'exercise_id' => $request->validated('exercise_id'),
            'order' => $request->validated('order') ?? WorkoutRowOrder::next($workoutTemplate),
            'target_sets' => $request->validated('target_sets') ?? 3,
            'min_target_reps' => $request->validated('min_target_reps') ?? 8,
            'max_target_reps' => $request->validated('max_target_reps') ?? 12,
            'target_weight' => $request->validated('target_weight') ?? 0,
            'rest_seconds' => $request->validated('rest_seconds') ?? 120,
        ]);

        return $this->back($workoutTemplate, $row, 'Exercise added.');
    }

    /**
     * There is no add-exercise page: rows are added in the outline.
     */
    public function create(WorkoutTemplate $workoutTemplate): RedirectResponse
    {
        return redirect(PlanOutline::adding($workoutTemplate));
    }

    /**
     * There is no row page: rows are edited in the outline.
     */
    public function edit(WorkoutTemplate $workoutTemplate, WorkoutTemplateExercise $workoutTemplateExercise): RedirectResponse
    {
        if ($workoutTemplateExercise->workout_template_id !== $workoutTemplate->id) {
            abort(403, 'Unauthorized.');
        }

        return redirect(PlanOutline::url($workoutTemplateExercise));
    }

    /**
     * Update the specified exercise in the workout template.
     */
    public function update(UpdateWorkoutTemplateExerciseRequest $request, WorkoutTemplate $workoutTemplate, WorkoutTemplateExercise $workoutTemplateExercise): RedirectResponse
    {
        if ($workoutTemplateExercise->workout_template_id !== $workoutTemplate->id) {
            abort(403, 'Unauthorized.');
        }

        // Only what was sent changes; an omitted field keeps its value.
        $workoutTemplateExercise->update(array_filter($request->validated(), fn ($value) => $value !== null));

        return $this->back($workoutTemplate, $workoutTemplateExercise, 'Exercise saved.');
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
        WorkoutRowOrder::close($workoutTemplate);

        return $this->back($workoutTemplate, null, 'Exercise removed.');
    }

    /**
     * Give a row another exercise from the plan's catalogue; its targets stay.
     * The route scopes the row to the workout.
     */
    public function swap(Request $request, WorkoutTemplate $workoutTemplate, WorkoutTemplateExercise $workoutTemplateExercise): RedirectResponse
    {
        $validated = $request->validate([
            'exercise_id' => ['required', 'integer', new ExerciseThePlanOffers($workoutTemplate->plan)],
        ]);

        $workoutTemplateExercise->update(['exercise_id' => $validated['exercise_id']]);

        return $this->back($workoutTemplate, $workoutTemplateExercise, 'Exercise swapped.');
    }

    /**
     * Move a row one place up or down. The route scopes the row to the workout.
     */
    public function move(Request $request, WorkoutTemplate $workoutTemplate, WorkoutTemplateExercise $workoutTemplateExercise): RedirectResponse
    {
        $validated = $request->validate(['direction' => ['required', 'in:up,down']]);

        WorkoutRowOrder::move($workoutTemplateExercise, $validated['direction'] === 'up' ? -1 : 1);

        return $this->back($workoutTemplate, null, null);
    }

    /**
     * Reopen the outline on the row, or on the workout when there is no row
     * to show.
     */
    private function back(WorkoutTemplate $workout, ?WorkoutTemplateExercise $row, ?string $message): RedirectResponse
    {
        $redirect = redirect(PlanOutline::url($row ?? $workout));

        return $message === null ? $redirect : $redirect->with('success', $message);
    }
}
