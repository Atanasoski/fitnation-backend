<?php

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\TrainingExperience;
use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\WorkoutGenerator\BlueprintWorkoutGenerator;
use App\Services\WorkoutGenerator\GeneratedDay;
use App\Services\WorkoutGenerator\Slots\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCatalogExercises;
use Tests\TestCase;

/**
 * The plan-level generator seam (#124): member + preferences + the ordered
 * list of day region-sets in, one generated day per region-set out — each
 * day resolved to its own Blueprint and filled on its own, from one shared
 * pool.
 */
class BlueprintPlanGenerationTest extends TestCase
{
    use CreatesCatalogExercises;
    use RefreshDatabase;

    private BlueprintWorkoutGenerator $generator;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(BlueprintWorkoutGenerator::class);

        $this->user = User::factory()->create();
        $this->user->profile->update([
            'fitness_goal' => FitnessGoal::GeneralFitness,
            'training_experience' => TrainingExperience::Intermediate,
        ]);
    }

    private function seedPushAndLowerCatalog(): void
    {
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'INCLINE');
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'VERTICAL');
        $this->catalogExercise('SHOULDER_RAISE', 'UPPER_PUSH', 'DUMBBELL');
        $this->catalogExercise('ELBOW_EXTENSION', 'ARMS', 'CABLE');
        $this->catalogExercise('SQUAT', 'LOWER', 'BARBELL');
        $this->catalogExercise('HINGE', 'LOWER', 'BARBELL');
        $this->catalogExercise('KNEE_EXTENSION', 'LOWER', 'MACHINE');
        $this->catalogExercise('KNEE_FLEXION', 'LOWER', 'MACHINE');
        $this->catalogExercise('CALF_RAISE', 'LOWER', 'MACHINE');
    }

    public function test_a_plan_returns_one_generated_day_per_region_set_in_order(): void
    {
        $this->seedPushAndLowerCatalog();

        $days = $this->generator->generatePlan($this->user, ['duration_minutes' => 45], [
            ['UPPER_PUSH'],
            ['LOWER'],
            ['UPPER_PUSH'],
        ]);

        $this->assertCount(3, $days);
        $this->assertContainsOnlyInstancesOf(GeneratedDay::class, $days);
        $this->assertSame(['PUSH', 'LOWER', 'PUSH'], array_map(fn (GeneratedDay $day) => $day->blueprint->code, $days));

        $this->assertSame(
            ['main_press', 'incline_press', 'overhead_press', 'side_delts', 'triceps'],
            array_column($days[0]->rows, 'slot_key'),
        );
        $this->assertSame(
            ['quad_compound', 'hinge', 'quad_iso', 'ham_iso', 'calves'],
            array_column($days[1]->rows, 'slot_key'),
        );

        foreach ($days as $day) {
            $this->assertSame(45, $day->durationMinutes);
            $this->assertNotSame('', $day->description);
            $this->assertSame([], $day->skippedSlots);
        }
    }

    public function test_history_resolved_once_reaches_every_day_that_picked_the_exercise(): void
    {
        $this->user->profile->update(['fitness_goal' => FitnessGoal::Strength]);
        $this->seedPushAndLowerCatalog();

        // The catalog's only FLAT press: both push days' main_press lands on it.
        $bench = Exercise::whereHas('angle', fn ($q) => $q->where('code', 'FLAT'))->sole();

        $session = WorkoutSession::factory()->create([
            'user_id' => $this->user->id,
            'status' => WorkoutSessionStatus::Completed,
            'completed_at' => now(),
        ]);
        foreach ([1, 2, 3] as $setNumber) {
            SetLog::create([
                'workout_session_id' => $session->id,
                'exercise_id' => $bench->id,
                'set_number' => $setNumber,
                'weight' => 50,
                'reps' => 12,
                'rest_seconds' => 150,
            ]);
        }

        $days = $this->generator->generatePlan($this->user, ['duration_minutes' => 45], [
            ['UPPER_PUSH'],
            ['LOWER'],
            ['UPPER_PUSH'],
        ]);

        foreach ([$days[0], $days[2]] as $pushDay) {
            $mainPress = collect($pushDay->rows)->firstWhere('slot_key', 'main_press');

            // Every set topped at 50kg ⇒ one barbell step up; logged rest carried.
            $this->assertSame($bench->id, $mainPress['exercise_id']);
            $this->assertEqualsWithDelta(52.5, (float) $mainPress['target_weight'], 0.001);
            $this->assertSame(150, $mainPress['rest_seconds']);
        }
    }

    public function test_each_day_reports_the_slots_it_skipped(): void
    {
        // No SHOULDER_RAISE, no UPPER_PUSH isolation, no bodyweight stand-in:
        // the push day's side_delts skips; the lower day fills completely.
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'INCLINE');
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'VERTICAL');
        $this->catalogExercise('ELBOW_EXTENSION', 'ARMS', 'CABLE');
        $this->catalogExercise('SQUAT', 'LOWER', 'BARBELL');
        $this->catalogExercise('HINGE', 'LOWER', 'BARBELL');
        $this->catalogExercise('KNEE_EXTENSION', 'LOWER', 'MACHINE');
        $this->catalogExercise('KNEE_FLEXION', 'LOWER', 'MACHINE');
        $this->catalogExercise('CALF_RAISE', 'LOWER', 'MACHINE');

        [$push, $lower] = $this->generator->generatePlan($this->user, ['duration_minutes' => 45], [
            ['UPPER_PUSH'],
            ['LOWER'],
        ]);

        $this->assertSame(['side_delts'], array_map(fn (Slot $slot) => $slot->key, $push->skippedSlots));
        $this->assertSame(
            ['main_press', 'incline_press', 'overhead_press', 'triceps'],
            array_column($push->rows, 'slot_key'),
        );
        $this->assertSame([], $lower->skippedSlots);
    }

    public function test_one_day_with_no_fillable_slot_fails_the_whole_plan(): void
    {
        // Nothing serves a pull day's regions, even after relaxation.
        $this->catalogExercise('SQUAT', 'LOWER', 'BARBELL');
        $this->catalogExercise('HINGE', 'LOWER', 'BARBELL');

        $this->expectExceptionMessage('No exercises available matching the specified criteria');

        $this->generator->generatePlan($this->user, ['duration_minutes' => 45], [
            ['LOWER'],
            ['UPPER_PULL'],
        ]);
    }
}
