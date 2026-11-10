<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Partner;
use Database\Seeders\CatalogEnrichmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #108 — catalog enrichment from the team's spreadsheet.
 *
 * The seeder's contract: the 37 exercises the production catalog lacked are
 * created fully classified (movement pattern, target region, equipment type,
 * angle where meaningful, muscle groups), band coverage above all — BAND was
 * a selectable equipment type with zero exercises. Names that already exist,
 * in any state including archived, are left untouched.
 *
 * Issue #130: every import joins the House Partner's library and no other
 * Partner Library; bands and kettlebells are FUNCTIONAL only.
 */
class CatalogEnrichmentSeederTest extends TestCase
{
    use RefreshDatabase;

    private Partner $house;

    protected function setUp(): void
    {
        parent::setUp();

        $this->house = Partner::factory()->create(['name' => 'Fit Nation']);
        config(['partners.house_partner_id' => $this->house->id]);
    }

    public function test_band_biceps_curl_is_imported_fully_classified(): void
    {
        $this->seed(CatalogEnrichmentSeeder::class);

        $exercise = Exercise::query()
            ->where('name', 'Band Biceps Curl')
            ->with(['movementPattern', 'targetRegion', 'equipmentType', 'muscleGroups'])
            ->first();

        $this->assertNotNull($exercise, 'Band Biceps Curl was not imported');
        $this->assertSame('ELBOW_FLEXION', $exercise->movementPattern->code);
        $this->assertSame('ARMS', $exercise->targetRegion->code);
        $this->assertSame('BAND', $exercise->equipmentType->code);
        $this->assertContains('Biceps', $exercise->muscleGroups->pluck('name')->all());
        $this->assertNotNull($exercise->description);
        $this->assertNotNull($exercise->default_rest_sec);
    }

    public function test_band_equipment_filter_returns_a_usable_set_spanning_push_pull_legs_and_arms(): void
    {
        $this->seed(CatalogEnrichmentSeeder::class);

        $bandExercises = Exercise::query()
            ->whereHas('equipmentType', fn ($q) => $q->where('code', 'BAND'))
            ->with('targetRegion')
            ->get();

        $this->assertGreaterThanOrEqual(10, $bandExercises->count());

        $regions = $bandExercises->pluck('targetRegion.code')->unique()->all();
        foreach (['UPPER_PUSH', 'UPPER_PULL', 'LOWER', 'ARMS'] as $required) {
            $this->assertContains($required, $regions, "No BAND exercise covers {$required}");
        }
    }

    public function test_all_thirty_seven_spreadsheet_exercises_are_imported_with_the_mapped_equipment(): void
    {
        $this->seed(CatalogEnrichmentSeeder::class);

        $countsByEquipment = Exercise::query()
            ->join('equipment_types', 'equipment_types.id', '=', 'workout_exercises.equipment_type_id')
            ->groupBy('equipment_types.code')
            ->selectRaw('equipment_types.code, COUNT(*) as total')
            ->pluck('total', 'code')
            ->map(fn ($n) => (int) $n)
            ->all();

        $this->assertSame([
            'BAND' => 12,
            'BARBELL' => 3,
            'BODYWEIGHT' => 1,
            'CABLE' => 5,
            'DUMBBELL' => 8,
            'KETTLEBELL' => 8,
        ], collect($countsByEquipment)->sortKeys()->all());

        $this->assertSame(37, Exercise::count());
    }

    public function test_every_imported_exercise_is_fully_classified(): void
    {
        $this->seed(CatalogEnrichmentSeeder::class);

        $exercises = Exercise::query()
            ->with(['category', 'muscleGroups', 'trainingStyles'])
            ->get();

        foreach ($exercises as $exercise) {
            $this->assertNotNull($exercise->movement_pattern_id, "{$exercise->name} has no movement pattern");
            $this->assertNotNull($exercise->target_region_id, "{$exercise->name} has no target region");
            $this->assertNotNull($exercise->equipment_type_id, "{$exercise->name} has no equipment type");
            $this->assertSame('strength-training', $exercise->category->slug, "{$exercise->name} has the wrong category");
            $this->assertNotEmpty($exercise->description, "{$exercise->name} has no description");
            $this->assertNotNull($exercise->default_rest_sec, "{$exercise->name} has no rest time");
            $this->assertTrue(
                $exercise->muscleGroups->where('pivot.is_primary', true)->isNotEmpty(),
                "{$exercise->name} has no primary muscle group"
            );
            $this->assertNotEmpty($exercise->trainingStyles, "{$exercise->name} has no training style");
        }
    }

    public function test_band_and_kettlebell_imports_are_functional_only_and_the_rest_bodybuilding(): void
    {
        $this->seed(CatalogEnrichmentSeeder::class);

        $exercises = Exercise::query()->with(['equipmentType', 'trainingStyles'])->get();

        $this->assertCount(37, $exercises);
        foreach ($exercises as $exercise) {
            $expected = in_array($exercise->equipmentType->code, ['BAND', 'KETTLEBELL'], true)
                ? ['FUNCTIONAL']
                : ['BODYBUILDING'];

            $this->assertSame(
                $expected,
                $exercise->trainingStyles->pluck('code')->sort()->values()->all(),
                "{$exercise->name} has the wrong training styles",
            );
        }
    }

    public function test_every_import_joins_the_house_partners_library_and_no_other(): void
    {
        $gym = Partner::factory()->create();
        $curated = Exercise::factory()->create(['name' => 'Gym Curated Press']);
        $gym->exercises()->attach($curated->id, ['description' => 'Our cue', 'image' => null, 'video' => null]);

        $this->seed(CatalogEnrichmentSeeder::class);

        $imports = Exercise::query()->whereKeyNot($curated->id)->get();
        $this->assertCount(37, $imports);

        $houseLibrary = $this->house->exercises()->get()->keyBy('id');
        $this->assertEqualsCanonicalizing($imports->modelKeys(), $houseLibrary->keys()->all());
        foreach ($houseLibrary as $exercise) {
            $this->assertNull($exercise->pivot->description, "{$exercise->name} was linked with an override");
            $this->assertNull($exercise->pivot->image, "{$exercise->name} was linked with an override");
            $this->assertNull($exercise->pivot->video, "{$exercise->name} was linked with an override");
        }

        $gymLibrary = $gym->exercises()->get();
        $this->assertSame([$curated->id], $gymLibrary->modelKeys());
        $this->assertSame('Our cue', $gymLibrary->first()->pivot->description);
    }

    public function test_arm_isolations_are_classified_under_arms_never_upper_push_or_pull(): void
    {
        $this->seed(CatalogEnrichmentSeeder::class);

        $armIsolations = Exercise::query()
            ->whereHas('movementPattern', fn ($q) => $q->whereIn('code', ['ELBOW_FLEXION', 'ELBOW_EXTENSION']))
            ->with('targetRegion')
            ->get();

        $this->assertNotEmpty($armIsolations);
        foreach ($armIsolations as $exercise) {
            $this->assertSame('ARMS', $exercise->targetRegion->code, "{$exercise->name} must live under ARMS");
        }
    }

    public function test_existing_rows_are_left_untouched_including_archived_ones(): void
    {
        // Production archived this spreadsheet name on purpose; the seeder
        // must neither resurrect nor reclassify it.
        $archived = Exercise::factory()->create([
            'name' => 'Dumbbell Triceps Kickback',
            'description' => 'Original description',
            'default_rest_sec' => 999,
        ]);
        $archived->forceFill(['archived_at' => now()])->save();

        $this->seed(CatalogEnrichmentSeeder::class);

        $this->assertSame(1, Exercise::where('name', 'Dumbbell Triceps Kickback')->count());

        $untouched = $archived->fresh();
        $this->assertNotNull($untouched->archived_at);
        $this->assertSame('Original description', $untouched->description);
        $this->assertSame(999, $untouched->default_rest_sec);
        $this->assertNull($untouched->movement_pattern_id);
        $this->assertFalse($this->house->exercises()->whereKey($archived->id)->exists(), 'An existing row was linked into the House library');
    }

    public function test_reseeding_creates_no_duplicates(): void
    {
        $this->seed(CatalogEnrichmentSeeder::class);
        $this->seed(CatalogEnrichmentSeeder::class);

        $this->assertSame(37, Exercise::count());
        $this->assertSame(
            Exercise::count(),
            Exercise::distinct('name')->count('name'),
        );
        $this->assertSame(37, $this->house->exercises()->count());
    }

    public function test_the_seeder_refuses_to_import_without_a_house_partner(): void
    {
        config(['partners.house_partner_id' => 999999]);

        $thrown = null;
        try {
            $this->seed(CatalogEnrichmentSeeder::class);
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'The seeder imported with no House Partner to link into.');
        $this->assertStringContainsString('House Partner', $thrown->getMessage());
        $this->assertSame(0, Exercise::count());
    }
}
