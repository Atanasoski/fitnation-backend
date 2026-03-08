<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Http\Requests\BulkLinkExerciseRequest;
use App\Http\Requests\StoreExerciseRequest;
use App\Http\Requests\UpdateExerciseRequest;
use App\Http\Requests\UpdatePartnerExerciseRequest;
use App\Models\Angle;
use App\Models\Category;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\MuscleGroup;
use App\Models\Partner;
use App\Models\TargetRegion;
use App\Models\TrainingStyle;
use App\Services\MuscleGroupImageService;
use App\Services\PartnerExerciseFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExerciseController extends Controller
{
    public function __construct(
        private PartnerExerciseFileService $fileService,
        private MuscleGroupImageService $muscleGroupImageService
    ) {}

    public function index()
    {
        $user = auth()->user();

        if (! $user->hasRole('admin')) {
            abort(403, 'Only system administrators can access this page.');
        }

        $equipmentTypes = EquipmentType::with(['exercises' => function ($query) {
            $query->with(['muscleGroups', 'partners', 'trainingStyles'])->orderBy('name');
        }])
            ->orderBy('display_order')
            ->get();

        $partners = Partner::orderBy('name')->get();

        return view('exercises.admin.index', compact('equipmentTypes', 'partners'));
    }

    public function partnerIndex()
    {
        $user = auth()->user();

        if (! $user->hasRole('partner_admin')) {
            abort(403, 'Only partner administrators can access this page.');
        }

        $partner = $user->partner;

        if (! $partner) {
            abort(403, 'You must be associated with a partner to view exercises.');
        }

        $categories = Category::where('type', CategoryType::Workout)
            ->with(['exercises' => function ($query) use ($partner) {
                $query->with(['partners' => function ($q) use ($partner) {
                    $q->where('partners.id', $partner->id)
                        ->withPivot(['description', 'image', 'video']);
                }, 'muscleGroups'])
                    ->orderBy('name');
            }])
            ->orderBy('display_order')
            ->get();

        // Add link status and prepare data for each exercise
        $linkedExerciseIds = $partner->exercises()->get()->modelKeys();
        foreach ($categories as $category) {
            foreach ($category->exercises as $exercise) {
                // Set link status
                $exercise->is_linked = in_array($exercise->id, $linkedExerciseIds);

                // Get pivot data if available
                $pivot = null;
                if ($exercise->relationLoaded('partners') && $exercise->partners->isNotEmpty()) {
                    $pivot = $exercise->partners->first()->pivot;
                }

                // Compute values for partner (pivot override or exercise default)
                $exercise->descriptionForPartner = $exercise->getDescription($partner);
                $exercise->imageForPartner = $exercise->getImage($partner);
                $exercise->videoForPartner = $exercise->getVideo($partner);

                // Store pivot data for editing forms
                $exercise->pivot_data = $pivot;
            }
        }

        return view('exercises.partner.index', compact('categories', 'partner'));
    }

    public function store(StoreExerciseRequest $request)
    {
        $exercise = Exercise::create([
            'name' => $request->name,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'movement_pattern_id' => $request->movement_pattern_id,
            'target_region_id' => $request->target_region_id,
            'equipment_type_id' => $request->equipment_type_id,
            'angle_id' => $request->angle_id,
            'default_rest_sec' => $request->default_rest_sec ?? 90,
        ]);

        // Sync muscle groups
        $muscleGroupAttachments = [];

        // Add primary muscle groups
        if ($request->has('primary_muscle_group_ids') && is_array($request->primary_muscle_group_ids)) {
            foreach ($request->primary_muscle_group_ids as $muscleGroupId) {
                $muscleGroupAttachments[$muscleGroupId] = ['is_primary' => true];
            }
        }

        // Add secondary muscle groups
        if ($request->has('secondary_muscle_group_ids') && is_array($request->secondary_muscle_group_ids)) {
            foreach ($request->secondary_muscle_group_ids as $muscleGroupId) {
                // If already in array as primary, skip (can't be both)
                if (! isset($muscleGroupAttachments[$muscleGroupId])) {
                    $muscleGroupAttachments[$muscleGroupId] = ['is_primary' => false];
                }
            }
        }

        $exercise->muscleGroups()->sync($muscleGroupAttachments);
        $exercise->trainingStyles()->sync($request->training_style_ids ?? []);

        return redirect()->route('exercises.index')
            ->with('success', 'Exercise created successfully!');
    }

    public function update(UpdateExerciseRequest $request, Exercise $exercise): RedirectResponse
    {
        $updateData = [
            'name' => $request->name,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'movement_pattern_id' => $request->movement_pattern_id,
            'target_region_id' => $request->target_region_id,
            'equipment_type_id' => $request->equipment_type_id,
            'angle_id' => $request->angle_id,
            'default_rest_sec' => $request->default_rest_sec,
        ];

        $oldFilesToDelete = [];

        if ($request->hasFile('image')) {
            if ($exercise->image) {
                $oldFilesToDelete[] = $exercise->image;
            }
            $updateData['image'] = $request->file('image')->store('exercises/images');
        }

        if ($request->hasFile('video')) {
            if ($exercise->video) {
                $oldFilesToDelete[] = $exercise->video;
            }
            $updateData['video'] = $request->file('video')->store('exercises/videos');
        }

        $muscleGroupAttachments = [];
        if ($request->has('primary_muscle_group_ids') && is_array($request->primary_muscle_group_ids)) {
            foreach ($request->primary_muscle_group_ids as $muscleGroupId) {
                $muscleGroupAttachments[$muscleGroupId] = ['is_primary' => true];
            }
        }
        if ($request->has('secondary_muscle_group_ids') && is_array($request->secondary_muscle_group_ids)) {
            foreach ($request->secondary_muscle_group_ids as $muscleGroupId) {
                if (! isset($muscleGroupAttachments[$muscleGroupId])) {
                    $muscleGroupAttachments[$muscleGroupId] = ['is_primary' => false];
                }
            }
        }

        DB::transaction(function () use ($exercise, $updateData, $muscleGroupAttachments, $request) {
            $exercise->update($updateData);
            $exercise->muscleGroups()->sync($muscleGroupAttachments);
            $exercise->trainingStyles()->sync($request->training_style_ids ?? []);
        });

        foreach ($oldFilesToDelete as $path) {
            Storage::delete($path);
        }

        return redirect()->route('exercises.show', $exercise)
            ->with('success', 'Exercise updated successfully!');
    }

    /**
     * Update partner exercise customization (pivot table).
     */
    public function updatePartnerExercises(UpdatePartnerExerciseRequest $request, Exercise $exercise): RedirectResponse
    {
        try {
            $partner = $request->user()->partner;

            $existingPivot = $partner->exercises()->find($exercise->id);
            $pivotData = $this->buildPartnerPivotData($request, $partner, $exercise, $existingPivot);

            DB::transaction(function () use ($partner, $exercise, $existingPivot, $pivotData) {
                if ($existingPivot) {
                    $partner->exercises()->updateExistingPivot($exercise->id, $pivotData);
                } else {
                    $partner->exercises()->attach($exercise->id, $pivotData);
                }
            });

            $removedMedia = $request->boolean('remove_video');

            return redirect()
                ->route($removedMedia ? 'partner.exercises.edit' : 'partner.exercises.show', $exercise)
                ->with('success', $removedMedia ? 'Custom video removed.' : 'Exercise customization updated successfully!');
        } catch (\Throwable $e) {
            Log::error('[ExerciseController] Failed to update partner exercise', [
                'error' => $e->getMessage(),
                'exercise_id' => $exercise->id,
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to save changes. Please try again.');
        }
    }

    /**
     * Build pivot data for partner exercise customization (description, image, video).
     *
     * @param  \App\Models\Exercise|null  $existingPivot  The related Exercise if the partner already has it attached, or null
     * @return array<string, mixed>
     */
    private function buildPartnerPivotData(
        UpdatePartnerExerciseRequest $request,
        Partner $partner,
        Exercise $exercise,
        ?Exercise $existingPivot,
    ): array {
        $pivot = $existingPivot?->pivot;

        $pivotData = [
            'description' => $pivot?->description,
            'image' => $pivot?->image,
            'video' => $pivot?->video,
        ];

        if ($request->has('description')) {
            $pivotData['description'] = $request->description ?: null;
        }

        if ($request->hasFile('image')) {
            $pivotData['image'] = $this->fileService->storeImage($request->file('image'), $partner, $exercise);
        }

        if ($request->hasFile('video')) {
            $pivotData['video'] = $this->fileService->storeVideo($request->file('video'), $partner, $exercise);
        } elseif ($request->boolean('remove_video') && $pivot?->video) {
            $this->fileService->deleteVideo($partner, $exercise);
            $pivotData['video'] = null;
        }

        return $pivotData;
    }

    /**
     * Show exercise details for admin.
     */
    public function adminShow(Exercise $exercise)
    {
        $user = auth()->user();

        if (! $user->hasRole('admin')) {
            abort(403, 'Only system administrators can access this page.');
        }

        // Load exercise with category relationship
        $exercise->load('category');

        return view('exercises.admin.show', compact('exercise'));
    }

    /**
     * Show edit form for admin exercise.
     */
    public function adminEdit(Exercise $exercise)
    {
        $user = auth()->user();

        if (! $user->hasRole('admin')) {
            abort(403, 'Only system administrators can edit exercises.');
        }

        // Load exercise with relationships
        $exercise->load(['category', 'movementPattern', 'targetRegion', 'equipmentType', 'angle', 'muscleGroups', 'trainingStyles']);

        // Get all categories for the dropdown
        $categories = Category::where('type', CategoryType::Workout)
            ->orderBy('display_order')
            ->get();

        $movementPatterns = MovementPattern::query()
            ->orderBy('display_order')
            ->get();

        $targetRegions = TargetRegion::query()
            ->orderBy('display_order')
            ->get();

        $equipmentTypes = EquipmentType::query()
            ->orderBy('display_order')
            ->get();

        $angles = Angle::query()
            ->orderBy('display_order')
            ->get();

        $muscleGroups = MuscleGroup::query()
            ->orderBy('body_region')
            ->orderBy('name')
            ->get();

        $trainingStyles = TrainingStyle::query()
            ->orderBy('display_order')
            ->get();

        // Get currently selected muscle group IDs
        $primaryMuscleGroupIds = $exercise->primaryMuscleGroups()->pluck('muscle_groups.id')->toArray();
        $secondaryMuscleGroupIds = $exercise->secondaryMuscleGroups()->pluck('muscle_groups.id')->toArray();
        $selectedTrainingStyleIds = $exercise->trainingStyles()->pluck('training_styles.id')->toArray();

        return view('exercises.admin.edit', compact(
            'exercise',
            'categories',
            'movementPatterns',
            'targetRegions',
            'equipmentTypes',
            'angles',
            'muscleGroups',
            'trainingStyles',
            'primaryMuscleGroupIds',
            'secondaryMuscleGroupIds',
            'selectedTrainingStyleIds',
        ));
    }

    /**
     * Show create form for admin exercise.
     */
    public function adminCreate()
    {
        $user = auth()->user();

        if (! $user->hasRole('admin')) {
            abort(403, 'Only system administrators can create exercises.');
        }

        $categories = Category::where('type', CategoryType::Workout)
            ->orderBy('display_order')
            ->get();

        $movementPatterns = MovementPattern::query()
            ->orderBy('display_order')
            ->get();

        $targetRegions = TargetRegion::query()
            ->orderBy('display_order')
            ->get();

        $equipmentTypes = EquipmentType::query()
            ->orderBy('display_order')
            ->get();

        $angles = Angle::query()
            ->orderBy('display_order')
            ->get();

        $muscleGroups = MuscleGroup::query()
            ->orderBy('body_region')
            ->orderBy('name')
            ->get();

        $trainingStyles = TrainingStyle::query()
            ->orderBy('display_order')
            ->get();

        return view('exercises.admin.create', compact(
            'categories',
            'movementPatterns',
            'targetRegions',
            'equipmentTypes',
            'angles',
            'muscleGroups',
            'trainingStyles',
        ));
    }

    public function destroy(Exercise $exercise)
    {
        $user = auth()->user();

        if (! $user->hasRole('admin')) {
            abort(403, 'Only system administrators can delete exercises.');
        }

        $exercise->delete();

        return redirect()->route('exercises.index')
            ->with('success', 'Exercise deleted successfully!');
    }

    /**
     * Update muscle group image for an exercise.
     */
    public function updateMuscleGroupImage(Exercise $exercise): JsonResponse
    {

        // Load muscle groups if not already loaded
        $exercise->load(['primaryMuscleGroups', 'secondaryMuscleGroups']);

        // Get muscle group names
        $primaryMuscles = $exercise->primaryMuscleGroups->pluck('name')->toArray();
        $secondaryMuscles = $exercise->secondaryMuscleGroups->pluck('name')->toArray();

        if (empty($primaryMuscles) && empty($secondaryMuscles)) {
            return response()->json(['error' => 'Exercise must have at least one muscle group assigned.'], 400);
        }

        // Delete old image if exists
        if ($exercise->muscle_group_image) {
            Storage::delete($exercise->muscle_group_image);
        }

        // Fetch and store new image
        $imagePath = $this->muscleGroupImageService->fetchAndStoreMuscleImage(
            $primaryMuscles,
            $secondaryMuscles
        );

        if ($imagePath === null) {
            return response()->json(['error' => 'Failed to fetch muscle group image. Please check API configuration.'], 500);
        }

        // Update exercise with new image path
        $exercise->update(['muscle_group_image' => $imagePath]);

        return response()->json([
            'success' => true,
            'message' => 'Muscle group image updated successfully!',
            'image_url' => Storage::url($imagePath),
        ]);
    }

    /**
     * Link an exercise to the user's partner.
     */
    public function linkExercise(Exercise $exercise): RedirectResponse
    {
        $user = auth()->user();
        $partner = $user->partner;

        if (! $partner) {
            abort(403, 'You must be associated with a partner to link exercises.');
        }

        // Link exercise to partner with null pivot values (will use exercise defaults)
        $partner->exercises()->syncWithoutDetaching([
            $exercise->id => [
                'description' => null,
                'image' => null,
                'video' => null,
            ],
        ]);

        return redirect()->back()
            ->with('success', 'Exercise linked successfully!');
    }

    /**
     * Unlink an exercise from the user's partner.
     */
    public function unlinkExercise(Exercise $exercise): RedirectResponse
    {
        $user = auth()->user();
        $partner = $user->partner;

        if (! $partner) {
            abort(403, 'You must be associated with a partner to unlink exercises.');
        }

        // Delete custom files if they exist
        $pivotData = $partner->exercises()->find($exercise->id);
        if ($pivotData && $pivotData->pivot) {
            if ($pivotData->pivot->image) {
                $this->fileService->deleteImage($partner, $exercise);
            }
            if ($pivotData->pivot->video) {
                $this->fileService->deleteVideo($partner, $exercise);
            }
        }

        // Unlink exercise from partner
        $partner->exercises()->detach($exercise->id);

        return redirect()->route('partner.exercises.index')
            ->with('success', 'Exercise unlinked successfully!');
    }

    /**
     * Show exercise details for partner.
     */
    public function show(Exercise $exercise)
    {
        $user = auth()->user();

        if (! $user->hasRole('partner_admin')) {
            abort(403, 'Only partner administrators can access this page.');
        }

        $partner = $user->partner;

        if (! $partner) {
            abort(403, 'You must be associated with a partner to view exercises.');
        }

        // Load exercise with partner pivot data
        $exercise->load(['partners' => function ($q) use ($partner) {
            $q->where('partners.id', $partner->id)
                ->withPivot(['description', 'image', 'video']);
        }, 'category']);

        // Get pivot data if available
        $pivot = null;
        if ($exercise->relationLoaded('partners') && $exercise->partners->isNotEmpty()) {
            $pivot = $exercise->partners->first()->pivot;
        }

        // Get values for partner (pivot override or exercise default)
        $descriptionForPartner = $exercise->getDescription($partner);
        $imageForPartner = $exercise->getImage($partner);
        $videoForPartner = $exercise->getVideo($partner);

        // Check if exercise is linked
        $isLinked = $partner->exercises()->where('workout_exercises.id', $exercise->id)->exists();

        return view('exercises.partner.show', compact('exercise', 'partner', 'pivot', 'descriptionForPartner', 'imageForPartner', 'videoForPartner', 'isLinked'));
    }

    /**
     * Show edit form for partner exercise customization.
     */
    public function edit(Exercise $exercise)
    {
        $user = auth()->user();

        if (! $user->hasRole('partner_admin')) {
            abort(403, 'Only partner administrators can access this page.');
        }

        $partner = $user->partner;

        if (! $partner) {
            abort(403, 'You must be associated with a partner to customize exercises.');
        }

        // Load exercise with partner pivot data
        $exercise->load(['partners' => function ($q) use ($partner) {
            $q->where('partners.id', $partner->id)
                ->withPivot(['description', 'image', 'video']);
        }, 'category']);

        // Get pivot data if available
        $pivot = null;
        if ($exercise->relationLoaded('partners') && $exercise->partners->isNotEmpty()) {
            $pivot = $exercise->partners->first()->pivot;
        }

        // Prepare form data (pivot values or defaults)
        $formDescription = $pivot?->description ?? '';
        $formImage = $pivot?->image ?? null;
        $formVideo = $pivot?->video ?? null;

        return view('exercises.partner.edit', compact('exercise', 'partner', 'pivot', 'formDescription', 'formImage', 'formVideo'));
    }

    /**
     * Bulk link exercises to partner.
     */
    public function bulkLink(BulkLinkExerciseRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $partner = $user->partner;

        $exerciseIds = $request->validated()['exercise_ids'];

        // Get currently linked exercise IDs
        $alreadyLinkedIds = $partner->exercises()->whereIn('workout_exercises.id', $exerciseIds)->pluck('workout_exercises.id')->toArray();

        // Prepare pivot data for all exercises (null values = use exercise defaults)
        $pivotData = [];
        foreach ($exerciseIds as $exerciseId) {
            $pivotData[$exerciseId] = [
                'description' => null,
                'image' => null,
                'video' => null,
            ];
        }

        // Link all exercises (syncWithoutDetaching won't duplicate already linked ones)
        $partner->exercises()->syncWithoutDetaching($pivotData);

        // Count newly linked exercises
        $newlyLinkedCount = count($exerciseIds) - count($alreadyLinkedIds);

        if ($newlyLinkedCount > 0) {
            return redirect()->route('partner.exercises.index')
                ->with('success', "{$newlyLinkedCount} exercise(s) linked successfully!");
        }

        return redirect()->route('partner.exercises.index')
            ->with('info', 'All selected exercises were already linked.');
    }
}
