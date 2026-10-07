<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkoutTemplateRequest;
use App\Http\Requests\UpdateWorkoutTemplateRequest;
use App\Models\Plan;
use App\Models\WorkoutTemplate;
use App\Services\Plan\PlanOutline;
use Illuminate\Http\RedirectResponse;

/**
 * A user's plan's workouts, written from the plan outline. There are no
 * workout pages: the old ones redirect to the outline. A library plan (no
 * user) never gets here: EnsureUserPlan 404s its routes.
 */
class WorkoutTemplateController extends Controller
{
    public function create(Plan $plan): RedirectResponse
    {
        return redirect(PlanOutline::adding($plan));
    }

    public function store(StoreWorkoutTemplateRequest $request, Plan $plan): RedirectResponse
    {
        $week = (int) ($request->validated('week_number') ?? 1);
        $orderIndex = WorkoutTemplate::where('plan_id', $plan->id)
            ->where('week_number', $week)
            ->count();

        $workoutTemplate = WorkoutTemplate::create([
            'plan_id' => $plan->id,
            'name' => $request->name,
            'description' => $request->description,
            'day_of_week' => $request->validated('day_of_week'),
            'week_number' => $week,
            'order_index' => $orderIndex,
        ]);

        return redirect(PlanOutline::url($workoutTemplate))->with('success', 'Workout added.');
    }

    public function show(WorkoutTemplate $workoutTemplate): RedirectResponse
    {
        return redirect(PlanOutline::url($workoutTemplate));
    }

    public function edit(WorkoutTemplate $workoutTemplate): RedirectResponse
    {
        return redirect(PlanOutline::url($workoutTemplate));
    }

    public function update(UpdateWorkoutTemplateRequest $request, WorkoutTemplate $workoutTemplate): RedirectResponse
    {
        // day_of_week (commented out): day-uniqueness swap logic removed
        $workoutTemplate->update($request->validated());

        return redirect(PlanOutline::url($workoutTemplate))->with('success', 'Workout saved.');
    }

    public function destroy(WorkoutTemplate $workoutTemplate): RedirectResponse
    {
        $plan = $workoutTemplate->plan;
        $workoutTemplate->delete();

        return redirect(PlanOutline::url($plan))->with('success', "{$workoutTemplate->name} removed.");
    }
}
