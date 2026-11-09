<?php

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\TrainingExperience;
use App\Models\Angle;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\Partner;
use App\Models\TargetRegion;
use App\Models\TrainingStyle;
use App\Models\User;
use App\Services\WorkoutGenerator\DeterministicWorkoutGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The seeded splits pair ARMS with one upper region on purpose:
 * ['UPPER_PUSH', 'ARMS'] is "Push + Triceps" and ['UPPER_PULL', 'ARMS'] is
 * "Pull + Biceps" (WorkoutSplitSeeder). The generator narrows the ARMS pool
 * to the paired region's complementary pattern, so a push day never draws a
 * curl and a pull day never draws a triceps extension. A day targeting ARMS
 * alone, or with both upper regions, keeps the full ARMS pool.
 */
class WorkoutGeneratorArmsDayTest extends TestCase
{
    use RefreshDatabase;

    private DeterministicWorkoutGenerator $generator;

    private Partner $partner;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(DeterministicWorkoutGenerator::class);

        $this->partner = Partner::factory()->create();
        $this->user = User::factory()->create(['partner_id' => $this->partner->id]);
        $this->user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);
    }

    private function attachToPartnerAndBodybuildingStyle(iterable $exercises): void
    {
        $bodybuilding = TrainingStyle::firstOrCreate(
            ['code' => 'BODYBUILDING'],
            ['code' => 'BODYBUILDING', 'name' => 'Bodybuilding', 'display_order' => 10]
        );

        foreach ($exercises as $exercise) {
            $exercise->partners()->syncWithoutDetaching([$this->partner->id]);
            $exercise->trainingStyles()->syncWithoutDetaching([$bodybuilding->id]);
        }
    }

    /**
     * An ARMS-region exercise: a curl (ELBOW_FLEXION) or a triceps extension
     * (ELBOW_EXTENSION), at an explicit angle and selection priority so the
     * pool order is deterministic.
     */
    private function createArmsExercise(string $name, string $patternCode, string $angleCode, int $priority = 100): Exercise
    {
        $pattern = MovementPattern::firstOrCreate(
            ['code' => $patternCode],
            ['name' => str_replace('_', ' ', ucwords(strtolower($patternCode), '_')), 'display_order' => 300]
        );
        $arms = TargetRegion::firstOrCreate(
            ['code' => 'ARMS'],
            ['name' => 'Arms', 'display_order' => 40]
        );
        $angle = Angle::firstOrCreate(
            ['code' => $angleCode],
            ['name' => ucfirst(strtolower($angleCode)), 'display_order' => 10]
        );

        return Exercise::factory()->withPriority($priority)->barbell()->create([
            'name' => $name,
            'movement_pattern_id' => $pattern->id,
            'target_region_id' => $arms->id,
            'angle_id' => $angle->id,
        ]);
    }

    /**
     * @return array<string, int> selected exercise count per raw movement pattern code
     */
    private function selectedPatternCounts(array $result): array
    {
        $counts = [];
        foreach ($result['exercises'] as $exerciseData) {
            $pattern = Exercise::find($exerciseData['exercise_id'])->movementPattern?->code ?? 'UNKNOWN';
            $counts[$pattern] = ($counts[$pattern] ?? 0) + 1;
        }

        return $counts;
    }

    public function test_push_arms_day_selects_triceps_extensions_and_no_curls(): void
    {
        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->incline()->create(['name' => 'Incline Bench Press']);
        Exercise::factory()->press()->barbell()->vertical()->create(['name' => 'Overhead Press']);
        Exercise::factory()->press()->barbell()->lowToHigh()->create(['name' => 'Landmine Press']);

        // Curls carry the highest priority: without day-aware filtering they
        // deterministically win every ARMS turn on this push day.
        $this->createArmsExercise('Hammer Curl', 'ELBOW_FLEXION', 'VERTICAL', priority: 200);
        $this->createArmsExercise('Barbell Curl', 'ELBOW_FLEXION', 'HORIZONTAL', priority: 200);
        $this->createArmsExercise('Overhead Triceps Extension', 'ELBOW_EXTENSION', 'VERTICAL', priority: 50);
        $this->createArmsExercise('Triceps Pushdown', 'ELBOW_EXTENSION', 'HORIZONTAL', priority: 50);

        $this->attachToPartnerAndBodybuildingStyle(Exercise::all());

        $result = $this->generator->generate($this->user, [
            'target_regions' => ['UPPER_PUSH', 'ARMS'],
            'duration_minutes' => 60,
        ]);

        $counts = $this->selectedPatternCounts($result);

        $this->assertArrayHasKey('ELBOW_EXTENSION', $counts, 'Push + Triceps day should include triceps-extension work');
        $this->assertArrayNotHasKey('ELBOW_FLEXION', $counts, 'Push + Triceps day must not draw curls');
        // The day still fills to the muscle-gain 60-minute target even though
        // the complementary-pattern import is skipped when ARMS is targeted.
        $this->assertCount(6, $result['exercises']);
    }

    public function test_pull_arms_day_selects_curls_and_no_triceps_extensions(): void
    {
        Exercise::factory()->row()->barbell()->horizontal()->create(['name' => 'Barbell Row']);
        Exercise::factory()->row()->barbell()->lowToHigh()->create(['name' => 'Landmine Row']);

        // Triceps extensions carry the highest priority: without day-aware
        // filtering they deterministically win every ARMS turn on this pull day.
        $this->createArmsExercise('Overhead Triceps Extension', 'ELBOW_EXTENSION', 'VERTICAL', priority: 200);
        $this->createArmsExercise('Triceps Pushdown', 'ELBOW_EXTENSION', 'HORIZONTAL', priority: 200);
        $this->createArmsExercise('Hammer Curl', 'ELBOW_FLEXION', 'VERTICAL', priority: 50);
        $this->createArmsExercise('Barbell Curl', 'ELBOW_FLEXION', 'HORIZONTAL', priority: 50);

        $this->attachToPartnerAndBodybuildingStyle(Exercise::all());

        $result = $this->generator->generate($this->user, [
            'target_regions' => ['UPPER_PULL', 'ARMS'],
            'duration_minutes' => 60,
        ]);

        $counts = $this->selectedPatternCounts($result);

        $this->assertArrayHasKey('ELBOW_FLEXION', $counts, 'Pull + Biceps day should include curls');
        $this->assertArrayNotHasKey('ELBOW_EXTENSION', $counts, 'Pull + Biceps day must not draw triceps extensions');
    }

    public function test_arms_only_day_keeps_the_full_arms_pool(): void
    {
        $this->createArmsExercise('Hammer Curl', 'ELBOW_FLEXION', 'VERTICAL');
        $this->createArmsExercise('Barbell Curl', 'ELBOW_FLEXION', 'HORIZONTAL');
        $this->createArmsExercise('Overhead Triceps Extension', 'ELBOW_EXTENSION', 'VERTICAL');
        $this->createArmsExercise('Triceps Pushdown', 'ELBOW_EXTENSION', 'HORIZONTAL');

        $this->attachToPartnerAndBodybuildingStyle(Exercise::all());

        $result = $this->generator->generate($this->user, [
            'target_regions' => ['ARMS'],
            'duration_minutes' => 60,
        ]);

        $counts = $this->selectedPatternCounts($result);

        $this->assertArrayHasKey('ELBOW_FLEXION', $counts, 'An ARMS-only day can select curls');
        $this->assertArrayHasKey('ELBOW_EXTENSION', $counts, 'An ARMS-only day can select triceps extensions');
    }

    public function test_arms_with_both_upper_regions_keeps_the_full_arms_pool(): void
    {
        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Barbell Bench Press']);
        Exercise::factory()->row()->barbell()->horizontal()->create(['name' => 'Barbell Row']);

        $this->createArmsExercise('Barbell Curl', 'ELBOW_FLEXION', 'VERTICAL');
        $this->createArmsExercise('Triceps Pushdown', 'ELBOW_EXTENSION', 'HORIZONTAL');

        $this->attachToPartnerAndBodybuildingStyle(Exercise::all());

        $result = $this->generator->generate($this->user, [
            'target_regions' => ['UPPER_PUSH', 'UPPER_PULL', 'ARMS'],
            'duration_minutes' => 60,
        ]);

        $counts = $this->selectedPatternCounts($result);

        $this->assertArrayHasKey('ELBOW_FLEXION', $counts, 'A full upper day can select curls');
        $this->assertArrayHasKey('ELBOW_EXTENSION', $counts, 'A full upper day can select triceps extensions');
    }

    public function test_at_most_two_curls_are_selected_on_a_pull_arms_day(): void
    {
        Exercise::factory()->row()->barbell()->horizontal()->create(['name' => 'Barbell Row']);
        Exercise::factory()->row()->barbell()->lowToHigh()->create(['name' => 'Landmine Row']);

        // Four curls at distinct angles: each passes the strict pattern|angle
        // dedupe, so only the per-pattern cap can stop a curl pile-up.
        $this->createArmsExercise('Barbell Curl', 'ELBOW_FLEXION', 'VERTICAL');
        $this->createArmsExercise('Incline Curl', 'ELBOW_FLEXION', 'INCLINE');
        $this->createArmsExercise('Spider Curl', 'ELBOW_FLEXION', 'HORIZONTAL');
        $this->createArmsExercise('Preacher Curl', 'ELBOW_FLEXION', 'FLAT');

        $this->attachToPartnerAndBodybuildingStyle(Exercise::all());

        $result = $this->generator->generate($this->user, [
            'target_regions' => ['UPPER_PULL', 'ARMS'],
            'duration_minutes' => 60,
        ]);

        $counts = $this->selectedPatternCounts($result);

        $this->assertSame(
            2,
            $counts['ELBOW_FLEXION'] ?? 0,
            'max_per_pattern_overrides caps ELBOW_FLEXION at 2 even with four distinct-angle curls available'
        );
    }
}
