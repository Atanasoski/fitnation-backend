<?php

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\TrainingExperience;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\User;
use App\Services\WorkoutGenerator\BlueprintWorkoutGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCatalogExercises;
use Tests\TestCase;

/**
 * Prescription precedence at the generator seam (ADR-0009/0010): logged
 * history outranks everything; without history a generated row reads reps
 * off its Slot and rest off the goal default; the ADR-0009 matrix survives
 * as the fallback for non-generated rows. Day shape itself is read off the
 * Blueprint and asserted in BlueprintWorkoutGeneratorTest, not here — the
 * legacy compound-cap cases died with the caps.
 */
class WorkoutGeneratorPrescriptionTest extends TestCase
{
    use CreatesCatalogExercises;
    use RefreshDatabase;

    private BlueprintWorkoutGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(BlueprintWorkoutGenerator::class);
    }

    private function makeUser(Partner $partner, FitnessGoal $goal, TrainingExperience $experience = TrainingExperience::Intermediate): User
    {
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => $goal,
            'training_experience' => $experience,
        ]);

        return $user;
    }

    private function attachToPartner(iterable $exercises, Partner $partner): void
    {
        foreach ($exercises as $exercise) {
            $exercise->partners()->syncWithoutDetaching([$partner->id]);
        }
    }

    public function test_no_history_rows_read_reps_off_the_slot_and_rest_off_the_scaled_default_rest(): void
    {
        $partner = Partner::factory()->create();
        $user = $this->makeUser($partner, FitnessGoal::MuscleGain);

        $press = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT', ['default_rest_sec' => 120]);
        $pushdown = $this->catalogExercise('ELBOW_EXTENSION', 'ARMS', 'CABLE', null, ['default_rest_sec' => 60]);
        $this->attachToPartner(Exercise::all(), $partner);

        $byId = collect($this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 30,
        ])['exercises'])->keyBy('exercise_id');

        // main_press slot: 6–10; triceps slot: 10–15. Rest is each
        // exercise's own Default Rest at the muscle-gain multiplier (× 1.0).
        $this->assertSame(6, $byId[$press->id]['min_target_reps']);
        $this->assertSame(10, $byId[$press->id]['max_target_reps']);
        $this->assertSame(120, $byId[$press->id]['rest_seconds']);

        $this->assertSame(10, $byId[$pushdown->id]['min_target_reps']);
        $this->assertSame(15, $byId[$pushdown->id]['max_target_reps']);
        $this->assertSame(60, $byId[$pushdown->id]['rest_seconds']);
    }

    /**
     * A fat-loss member's heavy lifts are not rushed: rest is the Default
     * Rest × 0.6, but never under the 60s floor of a compound Slot.
     */
    public function test_a_fat_loss_compound_slot_rests_its_scaled_default_rest_but_never_under_the_compound_floor(): void
    {
        $partner = Partner::factory()->create();
        $user = $this->makeUser($partner, FitnessGoal::FatLoss);

        $squat = $this->catalogExercise('SQUAT', 'LOWER', 'BARBELL', null, ['default_rest_sec' => 150]);
        $deadlift = $this->catalogExercise('HINGE', 'LOWER', 'BARBELL', null, ['default_rest_sec' => 90]);
        $this->attachToPartner(Exercise::all(), $partner);

        $byId = collect($this->generator->generate($user, [
            'target_regions' => ['LOWER'],
            'duration_minutes' => 30,
        ])['exercises'])->keyBy('exercise_id');

        $this->assertSame(90, $byId[$squat->id]['rest_seconds']); // 150 × 0.6
        $this->assertSame(60, $byId[$deadlift->id]['rest_seconds']); // 90 × 0.6 = 54 → compound floor 60
    }

    /**
     * A strength member's curl is not padded to a heavy lift's rest: it is
     * the curl's own Default Rest × 1.5.
     */
    public function test_a_strength_isolation_slot_rests_its_scaled_default_rest(): void
    {
        $partner = Partner::factory()->create();
        $user = $this->makeUser($partner, FitnessGoal::Strength);

        $curl = $this->catalogExercise('ELBOW_FLEXION', 'ARMS', 'DUMBBELL', null, ['default_rest_sec' => 60]);
        $this->attachToPartner(Exercise::all(), $partner);

        $byId = collect($this->generator->generate($user, [
            'target_regions' => ['UPPER_PULL'],
            'duration_minutes' => 30,
        ])['exercises'])->keyBy('exercise_id');

        $this->assertSame(90, $byId[$curl->id]['rest_seconds']); // 60 × 1.5
        $this->assertSame(10, $byId[$curl->id]['min_target_reps'], 'an isolation Slot keeps its range for strength');
        $this->assertSame(15, $byId[$curl->id]['max_target_reps']);
    }

    /**
     * Logged performance outranks the default Prescription: the user keeps
     * their own rest and progressed weight; only the rep-range goalposts come
     * from the matrix.
     */
    public function test_logged_history_outranks_the_default_prescription(): void
    {
        $partner = Partner::factory()->create();
        $user = $this->makeUser($partner, FitnessGoal::MuscleGain);

        $press = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT', ['default_rest_sec' => 120]);

        $targets = app(\App\Services\WorkoutGenerator\ProgressionCalculatorService::class)
            ->calculateTargetsFrom($press, $user, TrainingExperience::Intermediate, [
                'weight' => 60.0,
                'sets' => 4,
                'rest_seconds' => 100,
                'weights' => [60.0, 60.0, 60.0, 60.0],
                'reps' => [10, 10, 10, 10],
            ]);

        $this->assertSame(100, $targets['rest_seconds'], 'their own rest, not the scaled Default Rest');
        $this->assertSame(6, $targets['min_target_reps']);
        $this->assertSame(10, $targets['max_target_reps']);
        $this->assertGreaterThan(60.0, $targets['target_weight'], 'top of the range reached, weight moves up');
    }

    /**
     * A beginner is protected from advanced exercises; an exercise with no
     * difficulty yet counts as intermediate and stays reachable. Experience,
     * never gender, is what gates selection (ADR-0009).
     */
    public function test_a_beginner_never_receives_an_advanced_exercise(): void
    {
        $partner = Partner::factory()->create();
        $user = $this->makeUser($partner, FitnessGoal::MuscleGain, TrainingExperience::Beginner);

        $deadlift = $this->catalogExercise('ROW', 'UPPER_PULL', 'BARBELL', 'HORIZONTAL', [
            'name' => 'Deadlift',
            'difficulty' => 'advanced',
        ]);
        $this->catalogExercise('ROW', 'UPPER_PULL', 'BARBELL', 'LOW_TO_HIGH', [
            'name' => 'Landmine Row',
            'difficulty' => null,
        ]);
        $this->catalogExercise('ROW', 'UPPER_PULL', 'MACHINE', 'VERTICAL', [
            'name' => 'Machine Row',
            'difficulty' => 'beginner',
        ]);
        $this->attachToPartner(Exercise::all(), $partner);

        $selectedIds = collect($this->generator->generate($user, [
            'target_regions' => ['UPPER_PULL'],
            'duration_minutes' => 30,
        ])['exercises'])->pluck('exercise_id');

        $this->assertNotContains($deadlift->id, $selectedIds->all());
        $this->assertNotEmpty($selectedIds->all(), 'NULL and beginner difficulties stay reachable');
    }

    /**
     * Bodyweight (total_reps) targets carry null rep bounds, but template rows
     * are NOT NULL columns: a generated row's bounds come from its Slot, so
     * they are always integers.
     */
    public function test_a_bodyweight_exercise_gets_non_null_rep_bounds(): void
    {
        $partner = Partner::factory()->create();
        $user = $this->makeUser($partner, FitnessGoal::MuscleGain);

        $pushup = $this->catalogExercise('PUSHUP', 'UPPER_PUSH', 'BODYWEIGHT', 'HORIZONTAL', [
            'name' => 'Push-ups',
        ]);
        $this->attachToPartner(Exercise::all(), $partner);

        $byId = collect($this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 30,
        ])['exercises'])->keyBy('exercise_id');

        $this->assertIsInt($byId[$pushup->id]['min_target_reps']);
        $this->assertIsInt($byId[$pushup->id]['max_target_reps']);
    }

    /**
     * History without a logged rest falls back to the goal-scaled Default
     * Rest, not the raw column.
     */
    public function test_history_without_logged_rest_gets_the_scaled_rest(): void
    {
        $partner = Partner::factory()->create();
        $user = $this->makeUser($partner, FitnessGoal::FatLoss);

        $press = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT', ['default_rest_sec' => 120]);

        $targets = app(\App\Services\WorkoutGenerator\ProgressionCalculatorService::class)
            ->calculateTargetsFrom($press, $user, TrainingExperience::Intermediate, [
                'weight' => 60.0,
                'sets' => 3,
                'rest_seconds' => null,
                'weights' => [60.0, 60.0, 60.0],
                'reps' => [8, 8, 8],
            ]);

        $this->assertSame(75, $targets['rest_seconds']); // 120 × 0.6 = 72 → 75
    }
}
