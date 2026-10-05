<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkoutTemplateExerciseRequest;
use App\Http\Requests\UpdateWorkoutTemplateExerciseRequest;
use App\Models\Partner;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use App\Rules\ExerciseThePlanOffers;
use App\Services\Plan\PlanOutline;
use App\Services\Plan\WorkoutRowOrder;
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

        return $this->back($workoutTemplate, $row, 'Exercise added.', 'Exercise added successfully!');
    }

    /**
     * Show the form for editing the specified exercise in the workout template.
     */
    public function edit(WorkoutTemplate $workoutTemplate, WorkoutTemplateExercise $workoutTemplateExercise): View
    {
        $workoutTemplate->load('plan.user');
        if ($workoutTemplateExercise->workout_template_id !== $workoutTemplate->id) {
            abort(403, 'Unauthorized.');
        }

        $partner = Partner::with('identity')->findOrFail($workoutTemplate->plan->ownerPartnerId());
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

        // Only what was sent changes; an omitted field keeps its value.
        $workoutTemplateExercise->update(array_filter($request->validated(), fn ($value) => $value !== null));

        return $this->back($workoutTemplate, $workoutTemplateExercise, 'Exercise saved.', 'Exercise updated successfully!');
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

        return $this->back($workoutTemplate, null, 'Exercise removed.', 'Exercise removed successfully!');
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

        return $this->back($workoutTemplate, $workoutTemplateExercise, 'Exercise swapped.', 'Exercise swapped successfully!');
    }

    /**
     * Move a row one place up or down. The route scopes the row to the workout.
     */
    public function move(Request $request, WorkoutTemplate $workoutTemplate, WorkoutTemplateExercise $workoutTemplateExercise): RedirectResponse
    {
        $validated = $request->validate(['direction' => ['required', 'in:up,down']]);

        WorkoutRowOrder::move($workoutTemplateExercise, $validated['direction'] === 'up' ? -1 : 1);

        return $this->back($workoutTemplate, null, null, 'Exercise moved.');
    }

    /**
     * A user's plan reopens the outline on the row, or on the workout when
     * there is no row to show. A library plan keeps its own workout page.
     */
    private function back(WorkoutTemplate $workout, ?WorkoutTemplateExercise $row, ?string $outlineMessage, string $libraryMessage): RedirectResponse
    {
        if ($workout->plan->user_id === null) {
            return redirect()->route('workouts.show', $workout)->with('success', $libraryMessage);
        }

        $redirect = redirect(PlanOutline::url($row ?? $workout));

        return $outlineMessage === null ? $redirect : $redirect->with('success', $outlineMessage);
    }
}
