<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\User;
use App\Services\PlanFileService;
use App\Services\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function __construct(
        private PlanFileService $planFileService,
        private PlanService $planService
    ) {}

    /**
     * Show the form for creating a new plan for a user (user flow).
     */
    public function userPlanCreate(User $user): View
    {
        $partner = Partner::with('identity')->findOrFail($user->partner_id);

        return view('plans.users.create', compact('user', 'partner'));
    }

    /**
     * Display the specified plan (user flow: plan for a specific user).
     */
    public function userPlanShow(Plan $plan): View|RedirectResponse
    {
        // User plan show is only for plans assigned to a user; library plans use partner.programs.show
        if ($plan->user_id === null) {
            return redirect()->route('partner.programs.show', $plan);
        }

        $partner = Partner::with('identity')->findOrFail($plan->ownerPartnerId());

        $plan->load([
            'workoutTemplates' => function ($query) {
                $query->withCount('workoutTemplateExercises')
                    ->orderBy('week_number')
                    ->orderBy('order_index');
            },
        ]);

        $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        // Prepare exercise data for add exercise modal
        $workoutExerciseData = [];
        foreach ($plan->workoutTemplates as $workout) {
            // Get current exercise IDs in this workout template
            $currentExerciseIds = $workout->workoutTemplateExercises()->pluck('exercise_id')->toArray();

            // Get exercises available for this partner (excluding already added ones)
            $exercises = Exercise::whereHas('partners', function ($q) use ($partner) {
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

            $workoutExerciseData[$workout->id] = $exercises;
        }

        // Get all equipment types and muscle groups for filters
        $equipmentTypes = EquipmentType::orderBy('display_order')
            ->get(['id', 'name'])
            ->map(fn ($et) => ['id' => $et->id, 'name' => $et->name])
            ->values();

        $muscleGroups = MuscleGroup::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($mg) => ['id' => $mg->id, 'name' => $mg->name])
            ->values();

        $weeks = max(1, (int) ($plan->duration_weeks ?? 1));
        $workoutsByWeek = [];
        for ($w = 1; $w <= $weeks; $w++) {
            $workoutsByWeek[$w] = $plan->workoutTemplates
                ->where('week_number', $w)
                ->sortBy('order_index')
                ->values();
        }

        return view('plans.users.show', compact('plan', 'partner', 'dayNames', 'workoutExerciseData', 'equipmentTypes', 'muscleGroups', 'weeks', 'workoutsByWeek'));
    }

    /**
     * Show the form for editing the specified plan (user flow).
     */
    public function userPlanEdit(Plan $plan): View
    {
        $partner = Partner::with('identity')->findOrFail($plan->ownerPartnerId());

        return view('plans.users.edit', compact('plan', 'partner'));
    }

    // ===============================================
    // PARTNER LIBRARY PROGRAMS (CRUD)
    // ===============================================

    /**
     * Display a listing of partner library programs.
     */
    public function index(Request $request): View
    {
        $currentUser = $request->user();

        if (! $currentUser->hasRole('partner_admin')) {
            abort(403, 'Only partner administrators can manage programs.');
        }

        $partner = Partner::with('identity')->findOrFail($currentUser->partner_id);

        $plans = Plan::query()
            ->where('partner_id', $partner->id)
            ->whereNull('user_id')
            ->withCount('workoutTemplates')
            ->latest()
            ->paginate(15);

        return view('plans.index', compact('partner', 'plans'));
    }

    /**
     * Show the form for creating a new program (partner library).
     */
    public function create(Request $request): View
    {
        $currentUser = $request->user();

        if (! $currentUser->hasRole('partner_admin')) {
            abort(403);
        }

        $partner = Partner::with('identity')->findOrFail($currentUser->partner_id);

        return view('plans.create', compact('partner'));
    }

    /**
     * Store a newly created program in storage (partner library).
     */
    public function store(StorePlanRequest $request): RedirectResponse
    {
        $currentUser = $request->user();

        if (! $currentUser->hasRole('partner_admin')) {
            abort(403);
        }

        $partner = Partner::findOrFail($currentUser->partner_id);
        $attributes = $this->planService->createAttributes($request->validated(), 'library');
        if ($request->hasFile('cover_image')) {
            $attributes['cover_image'] = $this->planFileService->storeCoverImage($request->file('cover_image'), $partner);
        }
        $plan = Plan::create($attributes);

        return redirect()
            ->route('partner.programs.index')
            ->with('success', 'Program created successfully.');
    }

    /**
     * Display the specified program (partner library).
     */
    public function show(Plan $plan): View
    {
        $partner = Partner::with('identity')->findOrFail($plan->ownerPartnerId());

        $plan->load([
            'workoutTemplates' => function ($query) {
                $query->withCount('workoutTemplateExercises')
                    ->orderBy('week_number')
                    ->orderBy('order_index');
            },
        ]);

        $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        // Prepare exercise data for add exercise modal
        $workoutExerciseData = [];
        foreach ($plan->workoutTemplates as $workout) {
            // Get current exercise IDs in this workout template
            $currentExerciseIds = $workout->workoutTemplateExercises()->pluck('exercise_id')->toArray();

            // Get exercises available for this partner (excluding already added ones)
            $exercises = Exercise::whereHas('partners', function ($q) use ($partner) {
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

            $workoutExerciseData[$workout->id] = $exercises;
        }

        // Get all equipment types and muscle groups for filters
        $equipmentTypes = EquipmentType::orderBy('display_order')
            ->get(['id', 'name'])
            ->map(fn ($et) => ['id' => $et->id, 'name' => $et->name])
            ->values();

        $muscleGroups = MuscleGroup::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($mg) => ['id' => $mg->id, 'name' => $mg->name])
            ->values();

        $weeks = max(1, (int) ($plan->duration_weeks ?? 1));
        $workoutsByWeek = [];
        for ($w = 1; $w <= $weeks; $w++) {
            $workoutsByWeek[$w] = $plan->workoutTemplates
                ->where('week_number', $w)
                ->sortBy('order_index')
                ->values();
        }

        return view('plans.show', compact('plan', 'partner', 'dayNames', 'workoutExerciseData', 'equipmentTypes', 'muscleGroups', 'weeks', 'workoutsByWeek'));
    }

    /**
     * Show the form for editing the specified program (partner library).
     */
    public function edit(Plan $plan): View
    {
        $partner = Partner::with('identity')->findOrFail($plan->ownerPartnerId());

        return view('plans.edit', compact('plan', 'partner'));
    }

    /**
     * Update the specified program in storage (partner library).
     */
    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $data = collect($request->validated())->except('cover_image')->all();
        if ($request->hasFile('cover_image')) {
            $this->planFileService->deleteCoverImage($plan->cover_image);
            $plan->loadMissing('partner');
            $data['cover_image'] = $this->planFileService->storeCoverImage($request->file('cover_image'), $plan->partner);
        }
        $plan->update($data);

        return redirect()
            ->route('partner.programs.show', $plan)
            ->with('success', 'Program updated successfully.');
    }

    /**
     * Remove the specified program from storage (partner library).
     */
    public function destroy(Plan $plan): RedirectResponse
    {
        $plan->delete();

        return redirect()
            ->route('partner.programs.index')
            ->with('success', 'Program deleted successfully.');
    }
}
