<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkoutTemplateRequest;
use App\Http\Requests\UpdateWorkoutTemplateRequest;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\WorkoutTemplate;
use App\Services\Plan\PlanOutline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkoutTemplateController extends Controller
{
    /**
     * Show the form for creating a new workout template for a library plan.
     * A user's plan adds workouts in the outline.
     */
    public function create(Plan $plan): View|RedirectResponse
    {
        if ($plan->user_id !== null) {
            return redirect(PlanOutline::adding($plan));
        }

        $partner = Partner::with('identity')->findOrFail($plan->ownerPartnerId());

        return view('workout-templates.create', compact('plan', 'partner'));
    }

    /**
     * Store a newly created workout template in storage.
     */
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

        if ($plan->user_id !== null) {
            return redirect(PlanOutline::url($workoutTemplate))->with('success', 'Workout added.');
        }

        return redirect()->route('partner.programs.show', $plan)
            ->with('success', 'Workout template created successfully!');
    }

    /**
     * Display a library plan's workout template. A user's plan shows its
     * workouts in the outline.
     */
    public function show(WorkoutTemplate $workoutTemplate): View|RedirectResponse
    {
        if ($workoutTemplate->plan->user_id !== null) {
            return redirect(PlanOutline::url($workoutTemplate));
        }

        $partner = Partner::with('identity')->findOrFail($workoutTemplate->plan->ownerPartnerId());

        $workoutTemplate->load([
            'workoutTemplateExercises.exercise.category',
            'workoutTemplateExercises.exercise.muscleGroups',
        ]);

        // day_of_week (commented out): $dayNames / $dayName
        $dayName = null;
        $exercises = $workoutTemplate->workoutTemplateExercises->sortBy('order')->values();

        // Prepare exercise data for add exercise modal
        $currentExerciseIds = $workoutTemplate->workoutTemplateExercises->pluck('exercise_id')->toArray();

        $availableExercises = Exercise::whereHas('partners', function ($q) use ($partner) {
            $q->where('partners.id', $partner->id);
        })
            ->available()
            ->whereNotIn('id', $currentExerciseIds)
            ->with(['muscleGroups', 'primaryMuscleGroups', 'equipmentType'])
            ->orderBy('name')
            ->get()
            ->map(function ($exercise) {
                return [
                    'id' => $exercise->id,
                    'name' => $exercise->name,
                    'equipment_type_id' => $exercise->equipment_type_id,
                    'equipment_type_name' => $exercise->equipmentType?->name ?? 'Unknown',
                    'muscle_groups' => $exercise->muscleGroups->map(fn ($mg) => [
                        'id' => $mg->id,
                        'name' => $mg->name,
                    ])->values()->toArray(),
                    'primary_muscle_group_ids' => $exercise->primaryMuscleGroups->pluck('id')->values()->toArray(),
                ];
            })
            ->values();

        $equipmentTypes = EquipmentType::orderBy('display_order')
            ->get(['id', 'name'])
            ->map(fn ($et) => ['id' => $et->id, 'name' => $et->name])
            ->values();

        $muscleGroups = MuscleGroup::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($mg) => ['id' => $mg->id, 'name' => $mg->name])
            ->values();

        return view('workout-templates.show', compact('workoutTemplate', 'partner', 'dayName', 'exercises', 'availableExercises', 'equipmentTypes', 'muscleGroups'));
    }

    /**
     * Show the form for editing a library plan's workout template. A user's
     * plan edits its workouts in the outline.
     */
    public function edit(WorkoutTemplate $workoutTemplate): View|RedirectResponse
    {
        if ($workoutTemplate->plan->user_id !== null) {
            return redirect(PlanOutline::url($workoutTemplate));
        }

        $partner = Partner::with('identity')->findOrFail($workoutTemplate->plan->ownerPartnerId());

        return view('workout-templates.edit', compact('workoutTemplate', 'partner'));
    }

    /**
     * Update the specified workout template in storage.
     */
    public function update(UpdateWorkoutTemplateRequest $request, WorkoutTemplate $workoutTemplate): RedirectResponse
    {
        $validated = $request->validated();
        // day_of_week (commented out): day-uniqueness swap logic removed
        $workoutTemplate->update($validated);

        if ($workoutTemplate->plan->user_id !== null) {
            return redirect(PlanOutline::url($workoutTemplate))->with('success', 'Workout saved.');
        }

        return redirect()->route('plans.show', $workoutTemplate->plan)
            ->with('success', 'Workout template updated successfully!');
    }

    /**
     * Remove the specified workout template from storage.
     */
    public function destroy(Request $request, WorkoutTemplate $workoutTemplate): RedirectResponse
    {
        $workoutTemplate->load('plan.user');
        $plan = $workoutTemplate->plan;
        $isLibrary = $plan->user_id === null;
        $workoutTemplate->delete();

        if (! $isLibrary) {
            return redirect(PlanOutline::url($plan))->with('success', "{$workoutTemplate->name} removed.");
        }

        return redirect()->route('partner.programs.show', $plan)
            ->with('success', 'Workout template deleted successfully!');
    }

    /**
     * Return day-of-week options for the create/edit form (value, letter, title).
     * day_of_week commented out.
     *
     * @return array<int, array{value: int|string, letter: string, title: string}>
     */
    // private function dayOfWeekOptions(): array
    // {
    //     $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    //     $letters = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
    //     $options = [
    //         ['value' => '', 'letter' => '—', 'title' => 'Unassigned'],
    //     ];
    //     foreach ($dayNames as $index => $name) {
    //         $options[] = [
    //             'value' => $index,
    //             'letter' => $letters[$index],
    //             'title' => $name,
    //         ];
    //     }
    //
    //     return $options;
    // }
}
