<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Http\Requests\ExerciseRequest;
use App\Http\Requests\StoreExerciseRequest;
use App\Http\Requests\UpdateExerciseRequest;
use App\Models\Angle;
use App\Models\Category;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\MuscleGroup;
use App\Models\Partner;
use App\Models\TargetRegion;
use App\Models\TrainingStyle;
use App\Services\Exercise\ArchiveOutcome;
use App\Services\Exercise\ExerciseArchive;
use App\Services\Exercise\ExerciseGallery;
use App\Services\Exercise\PartnerExerciseView;
use App\Services\MuscleGroupImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ExerciseController extends Controller
{
    public function __construct(
        private MuscleGroupImageService $muscleGroupImageService
    ) {}

    /**
     * The exercise gallery (spec 023, ticket 03): a filtered, paginated slice
     * of the catalogue with facet counts. `?edit={id}` or `?create=1` opens
     * the editor in a slide-over over the same slice.
     */
    public function index(Request $request): View
    {
        $gallery = ExerciseGallery::fromQuery($request->query());

        $editing = null;
        if ($request->filled('edit')) {
            $editing = Exercise::with(['muscleGroups', 'trainingStyles'])->findOrFail($request->integer('edit'));
        } elseif ($request->boolean('create')) {
            $editing = new Exercise(['default_rest_sec' => 90, 'selection_priority' => 100]);
        }

        return view('exercises.admin.index', [
            'gallery' => $gallery,
            'exercises' => $gallery->exercises(),
            'facets' => $gallery->facets(),
            'page' => $request->integer('page') > 1 ? $request->integer('page') : null,
            'editing' => $editing,
            'lookups' => $editing ? $this->editorLookups() : null,
            'overrides' => $editing?->exists ? $this->partnerOverrides($editing) : null,
        ]);
    }

    /**
     * The slide-over's Partner Overrides: every partner linked to the
     * exercise with what its members see, and the partners it could be
     * linked to.
     *
     * @return array{linked: \Illuminate\Support\Collection<int, array{partner: Partner, view: PartnerExerciseView}>, unlinked: \Illuminate\Support\Collection<int, Partner>}
     */
    private function partnerOverrides(Exercise $exercise): array
    {
        $linked = $exercise->loadMissing('partners')->partners->sortBy('name')->values();

        return [
            'linked' => $linked->map(fn (Partner $partner) => [
                'partner' => $partner,
                'view' => PartnerExerciseView::of($exercise, $partner),
            ]),
            'unlinked' => Partner::whereKeyNot($linked->modelKeys())->orderBy('name')->get(['id', 'name']),
        ];
    }

    public function store(StoreExerciseRequest $request): RedirectResponse
    {
        $attributes = $this->exerciseAttributes($request) + [
            'default_rest_sec' => $request->default_rest_sec ?? 90,
            'selection_priority' => $request->selection_priority ?? 100,
        ];

        if ($request->hasFile('image')) {
            $attributes['image'] = $request->file('image')->store('exercises/images');
        }
        if ($request->hasFile('video')) {
            $attributes['video'] = $request->file('video')->store('exercises/videos');
        }

        DB::transaction(function () use ($attributes, $request) {
            $exercise = Exercise::create($attributes);
            $this->syncClassification($exercise, $request);
        });

        return redirect()->to($this->galleryUrl($request))
            ->with('success', 'Exercise created successfully!');
    }

    public function update(UpdateExerciseRequest $request, Exercise $exercise): RedirectResponse
    {
        $updateData = $this->exerciseAttributes($request) + [
            'default_rest_sec' => $request->default_rest_sec,
        ];
        if ($request->filled('selection_priority')) {
            $updateData['selection_priority'] = $request->selection_priority;
        }

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

        DB::transaction(function () use ($exercise, $updateData, $request) {
            $exercise->update($updateData);
            $this->syncClassification($exercise, $request);
        });

        foreach ($oldFilesToDelete as $path) {
            Storage::delete($path);
        }

        return redirect()->to($this->galleryUrl($request))
            ->with('success', 'Exercise updated successfully!');
    }

    /**
     * The exercise columns a create or update form sets directly.
     *
     * @return array<string, mixed>
     */
    private function exerciseAttributes(ExerciseRequest $request): array
    {
        return [
            'name' => $request->name,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'movement_pattern_id' => $request->movement_pattern_id,
            'target_region_id' => $request->target_region_id,
            'equipment_type_id' => $request->equipment_type_id,
            'angle_id' => $request->angle_id,
            'difficulty' => $request->difficulty ?: null,
        ];
    }

    /**
     * Muscle groups (a group chosen as both primary and secondary stays
     * primary) and training styles, as the form sent them.
     */
    private function syncClassification(Exercise $exercise, ExerciseRequest $request): void
    {
        $muscleGroups = [];
        foreach ((array) $request->input('primary_muscle_group_ids', []) as $id) {
            $muscleGroups[$id] = ['is_primary' => true];
        }
        foreach ((array) $request->input('secondary_muscle_group_ids', []) as $id) {
            $muscleGroups[$id] ??= ['is_primary' => false];
        }

        $exercise->muscleGroups()->sync($muscleGroups);
        $exercise->trainingStyles()->sync($request->training_style_ids ?? []);
    }

    /**
     * The old exercise page: the gallery with the exercise open.
     */
    public function adminShow(Exercise $exercise): RedirectResponse
    {
        return redirect()->route('exercises.index', ['edit' => $exercise->id]);
    }

    /**
     * The old edit page: the gallery with the exercise open.
     */
    public function adminEdit(Exercise $exercise): RedirectResponse
    {
        return redirect()->route('exercises.index', ['edit' => $exercise->id]);
    }

    /**
     * The old create page: the gallery with an empty editor open.
     */
    public function adminCreate(): RedirectResponse
    {
        return redirect()->route('exercises.index', ['create' => 1]);
    }

    /**
     * Everything the editor's selects and chips offer.
     *
     * @return array<string, \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>>
     */
    private function editorLookups(): array
    {
        return [
            'categories' => Category::where('type', CategoryType::Workout)->orderBy('display_order')->get(),
            'movementPatterns' => MovementPattern::orderBy('display_order')->get(),
            'targetRegions' => TargetRegion::orderBy('display_order')->get(),
            'equipmentTypes' => EquipmentType::orderBy('display_order')->get(),
            'angles' => Angle::orderBy('display_order')->get(),
            'muscleGroups' => MuscleGroup::orderBy('body_region')->orderBy('name')->get(),
            'trainingStyles' => TrainingStyle::orderBy('display_order')->get(),
        ];
    }

    /**
     * The gallery slice a form was posted from (its `back` field).
     */
    private function galleryUrl(Request $request): string
    {
        return ExerciseGallery::backUrl($request->input('back'));
    }

    public function destroy(Request $request, Exercise $exercise): RedirectResponse
    {
        $message = ExerciseArchive::archiveOrDelete($exercise) === ArchiveOutcome::Archived
            ? 'Exercise archived. It is used in plans or logged sessions, which keep it.'
            : 'Exercise deleted successfully!';

        return redirect()->to($this->galleryUrl($request))->with('success', $message);
    }

    /**
     * Archive or delete the selected exercises, each by the Archived
     * Exercise rule, and say how many went which way.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'exercise_ids' => ['required', 'array', 'min:1'],
            'exercise_ids.*' => ['integer'],
        ])['exercise_ids'];

        $counts = ExerciseArchive::archiveOrDeleteMany($ids);

        return redirect()->to($this->galleryUrl($request))->with('success', sprintf(
            '%d archived (used in plans or logged sessions), %d deleted.',
            $counts['archived'],
            $counts['deleted'],
        ));
    }

    /**
     * Put an Archived Exercise back in the catalogue.
     */
    public function restore(Request $request, Exercise $exercise): RedirectResponse
    {
        ExerciseArchive::restore($exercise);

        return redirect()->to($this->galleryUrl($request))->with('success', 'Exercise restored to the catalogue.');
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
}
