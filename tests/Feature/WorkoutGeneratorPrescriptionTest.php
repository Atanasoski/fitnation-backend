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
 * ADR-0009: day shape comes from per-region compound caps, not aliases, and a
 * no-history Prescription comes from the compound/isolation × goal matrix with
 * rest scaled from the exercise's Default Rest.
 */
class WorkoutGeneratorPrescriptionTest extends TestCase
{
    use RefreshDatabase;

    private DeterministicWorkoutGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(DeterministicWorkoutGenerator::class);
    }

    private function attachToPartnerAndBodybuildingStyle(iterable $exercises, Partner $partner): void
    {
        $bodybuilding = TrainingStyle::firstOrCreate(
            ['code' => 'BODYBUILDING'],
            ['code' => 'BODYBUILDING', 'name' => 'Bodybuilding', 'display_order' => 10]
        );

        foreach ($exercises as $exercise) {
            $exercise->partners()->syncWithoutDetaching([$partner->id]);
            $exercise->trainingStyles()->syncWithoutDetaching([$bodybuilding->id]);
        }
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

    private function isolation(string $name, string $patternCode, string $regionCode, ?string $angleCode = null, array $attributes = []): Exercise
    {
        $state = [
            'name' => $name,
            'movement_pattern_id' => MovementPattern::firstOrCreate(
                ['code' => $patternCode], ['name' => $patternCode, 'display_order' => 50]
            )->id,
            'target_region_id' => TargetRegion::firstOrCreate(
                ['code' => $regionCode], ['name' => $regionCode, 'display_order' => 50]
            )->id,
        ];

        if ($angleCode !== null) {
            $state['angle_id'] = Angle::firstOrCreate(
                ['code' => $angleCode], ['name' => $angleCode, 'display_order' => 50]
            )->id;
        }

        return Exercise::factory()->create($state + $attributes);
    }

    public function test_a_push_day_paired_with_arms_holds_at_most_two_compounds(): void
    {
        $partner = Partner::factory()->create();
        $user = $this->makeUser($partner, FitnessGoal::MuscleGain);

        // Four presses at distinct angles: four selectable compound slots.
        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->incline()->create(['name' => 'Incline Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->vertical()->create(['name' => 'Overhead Barbell Press']);
        Exercise::factory()->press()->barbell()->lowToHigh()->create(['name' => 'Landmine Press']);

        $raise = $this->isolation('Dumbbell Lateral Raises', 'SHOULDER_RAISE', 'UPPER_PUSH', 'HORIZONTAL');
        $fly = $this->isolation('Cable Fly', 'FLY', 'UPPER_PUSH', 'FLAT');
        $this->isolation('Triceps Pushdown', 'ELBOW_EXTENSION', 'ARMS');
        $this->isolation('Skull Crushers', 'ELBOW_EXTENSION', 'ARMS', 'FLAT');

        $this->attachToPartnerAndBodybuildingStyle(Exercise::all(), $partner);

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH', 'ARMS'],
            'duration_minutes' => 60,
        ]);

        $selected = Exercise::findMany(collect($result['exercises'])->pluck('exercise_id'));
        $compounds = $selected->filter(
            fn (Exercise $e) => in_array($e->movementPattern->code, config('workout_generator.compound_patterns'))
        );

        $this->assertCount(2, $compounds, 'compounds must stop at the UPPER_PUSH cap');
        $this->assertContains($raise->id, $selected->pluck('id')->all(), 'the freed slot goes to the raise');
        $this->assertContains($fly->id, $selected->pluck('id')->all(), 'and the fly');
    }

    public function test_no_history_prescriptions_follow_the_matrix_and_the_exercise_default_rest(): void
    {
        $partner = Partner::factory()->create();
        $user = $this->makeUser($partner, FitnessGoal::MuscleGain);

        $press = Exercise::factory()->press()->barbell()->flat()->create([
            'name' => 'Barbell Bench Press',
            'default_rest_sec' => 120,
        ]);
        $fly = $this->isolation('Cable Fly', 'FLY', 'UPPER_PUSH', 'FLAT', ['default_rest_sec' => 60]);

        $this->attachToPartnerAndBodybuildingStyle(Exercise::all(), $partner);

        $byId = collect($this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 30,
        ])['exercises'])->keyBy('exercise_id');

        $this->assertSame(6, $byId[$press->id]['min_target_reps']);
        $this->assertSame(10, $byId[$press->id]['max_target_reps']);
        $this->assertSame(120, $byId[$press->id]['rest_seconds']);

        $this->assertSame(10, $byId[$fly->id]['min_target_reps']);
        $this->assertSame(15, $byId[$fly->id]['max_target_reps']);
        $this->assertSame(60, $byId[$fly->id]['rest_seconds']);
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

        $press = Exercise::factory()->press()->barbell()->flat()->create([
            'name' => 'Barbell Bench Press',
            'default_rest_sec' => 120,
        ]);

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

        $deadlift = Exercise::factory()->row()->barbell()->horizontal()->create([
            'name' => 'Deadlift',
            'difficulty' => 'advanced',
        ]);
        Exercise::factory()->row()->barbell()->lowToHigh()->create([
            'name' => 'Landmine Row',
            'difficulty' => null,
        ]);
        Exercise::factory()->row()->barbell()->vertical()->create([
            'name' => 'Machine Row',
            'difficulty' => 'beginner',
        ]);

        $this->attachToPartnerAndBodybuildingStyle(Exercise::all(), $partner);

        $selectedIds = collect($this->generator->generate($user, [
            'target_regions' => ['UPPER_PULL'],
            'duration_minutes' => 30,
        ])['exercises'])->pluck('exercise_id');

        $this->assertNotContains($deadlift->id, $selectedIds->all());
        $this->assertNotEmpty($selectedIds->all(), 'NULL and beginner difficulties stay reachable');
    }
}
