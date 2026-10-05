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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkoutTemplateController extends Controller
{
    /**
     * Show the form for creating a new workout template for a plan.
     */
    public function create(Plan $plan): View
    {
        $plan->load('user');
        $partner = Partner::with('identity')->findOrFail($plan->ownerPartnerId());
        $isLibrary = $plan->user_id === null;
        $user = $isLibrary ? null : $plan->user;

        // day_of_week (commented out)
        // $dayOfWeekOptions = $this->dayOfWeekOptions();
        // $dayOfWeekValue = $request->old('day_of_week');

        $view = $isLibrary ? 'workout-templates.create' : 'workout-templates.users.create';

        return view($view, compact('plan', 'partner', 'isLibrary', 'user'));
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
            'week_number' => $week,
            'order_index' => $orderIndex,
        ]);

        $planShowRoute = $plan->user_id ? 'plans.show' : 'partner.programs.show';

        return redirect()->route($planShowRoute, $plan)
            ->with('success', 'Workout template created successfully!');
    }

    /**
     * Display the specified workout template.
     */
    public function show(WorkoutTemplate $workoutTemplate): View
    {
        $workoutTemplate->load('plan.user');
        $partner = Partner::with('identity')->findOrFail($workoutTemplate->plan->ownerPartnerId());
        $isLibrary = $workoutTemplate->plan->user_id === null;
        $user = $isLibrary ? null : $workoutTemplate->plan->user;

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

        $view = $isLibrary ? 'workout-templates.show' : 'workout-templates.users.show';

        return view($view, compact('workoutTemplate', 'partner', 'dayName', 'exercises', 'availableExercises', 'equipmentTypes', 'muscleGroups', 'isLibrary', 'user'));
    }

    /**
     * Show the form for editing the specified workout template.
     */
    public function edit(WorkoutTemplate $workoutTemplate): View
    {
        $workoutTemplate->load('plan.user');
        $partner = Partner::with('identity')->findOrFail($workoutTemplate->plan->ownerPartnerId());
        $isLibrary = $workoutTemplate->plan->user_id === null;
        $user = $isLibrary ? null : $workoutTemplate->plan->user;

        // day_of_week (commented out)
        // $dayOfWeekOptions = $this->dayOfWeekOptions();
        // $dayOfWeekValue = $request->old('day_of_week', $workoutTemplate->day_of_week);

        $view = $isLibrary ? 'workout-templates.edit' : 'workout-templates.users.edit';

        return view($view, compact('workoutTemplate', 'partner', 'isLibrary', 'user'));
    }

    /**
     * Update the specified workout template in storage.
     */
    public function update(UpdateWorkoutTemplateRequest $request, WorkoutTemplate $workoutTemplate): RedirectResponse
    {
        $validated = $request->validated();
        // day_of_week (commented out): day-uniqueness swap logic removed
        $workoutTemplate->update($validated);

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

        $redirectRoute = $isLibrary ? 'partner.programs.show' : 'plans.show';

        return redirect()->route($redirectRoute, $plan)
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
