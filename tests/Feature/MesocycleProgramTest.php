<?php

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\MovementClass;
use App\Enums\SplitFocus;
use App\Enums\TrainingExperience;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\Partner;
use App\Models\TargetRegion;
use App\Models\User;
use App\Models\WorkoutSplit;
use App\Services\WelcomePlanGenerationService;
use Database\Seeders\WorkoutSplitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A generated Program is one Mesocycle: identical accumulation weeks ending in
 * a Deload Week, length driven by the fitness goal. See ADR-0007.
 */
class MesocycleProgramTest extends TestCase
{
    use RefreshDatabase;

    private Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorkoutSplitSeeder::class);

        $this->partner = Partner::factory()->create();

        // A catalog big enough for the generator to fill any split day.
        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->incline()->create(['name' => 'Incline Bench Press']);
        Exercise::factory()->press()->barbell()->vertical()->create(['name' => 'Overhead Press']);
        Exercise::factory()->row()->barbell()->horizontal()->create(['name' => 'Barbell Row']);
        Exercise::factory()->row()->barbell()->lowToHigh()->create(['name' => 'Landmine Row']);

        // An isolation movement, so the deload's compound/isolation split is
        // observable. With five compounds and a six-exercise target, this curl
        // is always selected.
        $elbowFlexion = MovementPattern::firstOrCreate(
            ['code' => 'ELBOW_FLEXION'],
            ['name' => 'Elbow Flexion', 'display_order' => 200]
        );
        $upperPull = TargetRegion::firstOrCreate(
            ['code' => 'UPPER_PULL'],
            ['name' => 'Upper Pull', 'display_order' => 20]
        );
        Exercise::factory()->barbell()->create([
            'name' => 'Barbell Curl',
            'movement_pattern_id' => $elbowFlexion->id,
            'target_region_id' => $upperPull->id,
        ]);

        foreach (Exercise::all() as $exercise) {
            $exercise->partners()->syncWithoutDetaching([$this->partner->id]);
        }
    }

    private function makeUser(FitnessGoal $goal): User
    {
        $user = User::factory()->create(['partner_id' => $this->partner->id]);
        $user->profile->update([
            'fitness_goal' => $goal,
            'training_experience' => TrainingExperience::Intermediate,
            'training_days_per_week' => 3,
            'workout_duration_minutes' => 60,
            'gender' => Gender::Male,
        ]);

        return $user->refresh();
    }

    public function test_generated_template_rows_persist_the_slot_key_that_produced_them(): void
    {
        $service = app(WelcomePlanGenerationService::class);

        $plan = $service->generatePlan($this->makeUser(FitnessGoal::MuscleGain));
        $plan->load('workoutTemplates.workoutTemplateExercises');

        $slotKeys = $plan->workoutTemplates
            ->flatMap(fn ($template) => $template->workoutTemplateExercises->pluck('slot_key'));

        $this->assertNotEmpty($slotKeys);
        $this->assertFalse($slotKeys->contains(null), 'Every generated template row carries its slot key');
    }

    public function test_program_length_comes_from_the_fitness_goal(): void
    {
        $service = app(WelcomePlanGenerationService::class);

        $plan = $service->generatePlan($this->makeUser(FitnessGoal::MuscleGain));

        $this->assertSame(8, $plan->duration_weeks);
        $this->assertSame(
            range(1, 8),
            $plan->workoutTemplates->pluck('week_number')->unique()->sort()->values()->all()
        );
    }

    public function test_fat_loss_gets_a_six_week_mesocycle(): void
    {
        $service = app(WelcomePlanGenerationService::class);

        $plan = $service->generatePlan($this->makeUser(FitnessGoal::FatLoss));

        $this->assertSame(6, $plan->duration_weeks);
        $this->assertSame(6, $plan->workoutTemplates->max('week_number'));
    }

    public function test_accumulation_weeks_carry_identical_workouts(): void
    {
        $service = app(WelcomePlanGenerationService::class);

        $plan = $service->generatePlan($this->makeUser(FitnessGoal::MuscleGain));

        $rowsFor = function (int $week, int $day) use ($plan): array {
            return $plan->workoutTemplates
                ->where('week_number', $week)
                ->where('day_of_week', $day)
                ->first()
                ->workoutTemplateExercises
                ->sortBy('order')
                ->map(fn ($row) => [
                    $row->exercise_id,
                    $row->target_sets,
                    $row->min_target_reps,
                    $row->max_target_reps,
                ])
                ->values()
                ->all();
        };

        $plan->load('workoutTemplates.workoutTemplateExercises');

        foreach ([0, 1, 2] as $day) {
            $weekOne = $rowsFor(1, $day);
            $this->assertNotEmpty($weekOne);

            foreach (range(2, 7) as $week) {
                $this->assertSame($weekOne, $rowsFor($week, $day), "Week {$week}, day {$day} differs from week 1");
            }
        }
    }

    public function test_final_week_is_a_deload(): void
    {
        $service = app(WelcomePlanGenerationService::class);

        $plan = $service->generatePlan($this->makeUser(FitnessGoal::MuscleGain));
        $plan->load('workoutTemplates.workoutTemplateExercises.exercise.movementPattern');

        $deloadTemplates = $plan->workoutTemplates->where('week_number', 8);
        $this->assertCount(3, $deloadTemplates);

        $isolationRowsSeen = 0;

        foreach ($deloadTemplates as $template) {
            $this->assertStringEndsWith(' (Deload)', $template->name);
            $this->assertTrue($template->is_deload);

            $weekOne = $plan->workoutTemplates
                ->where('week_number', 1)
                ->where('day_of_week', $template->day_of_week)
                ->first();
            $this->assertFalse($weekOne->is_deload);

            // Same exercises, same order as the accumulation weeks.
            $this->assertSame(
                $weekOne->workoutTemplateExercises->sortBy('order')->pluck('exercise_id')->values()->all(),
                $template->workoutTemplateExercises->sortBy('order')->pluck('exercise_id')->values()->all()
            );

            foreach ($template->workoutTemplateExercises as $row) {
                $this->assertSame(2, $row->target_sets);

                $isCompound = MovementClass::fromPattern(
                    $row->exercise->movementPattern?->code
                )->isCompound();

                if ($isCompound) {
                    $this->assertSame(3, $row->min_target_reps);
                    $this->assertSame(4, $row->max_target_reps);
                } else {
                    $isolationRowsSeen++;
                    $weekOneRow = $weekOne->workoutTemplateExercises
                        ->firstWhere('exercise_id', $row->exercise_id);
                    $this->assertSame($weekOneRow->min_target_reps, $row->min_target_reps);
                    $this->assertSame($weekOneRow->max_target_reps, $row->max_target_reps);
                }
            }
        }

        // The isolation branch must actually have been exercised.
        $this->assertGreaterThan(0, $isolationRowsSeen);
    }

    /**
     * A workout's name and description come from the same resolved day
     * (#131): push+pull+arms is an Upper day in both, a composed day is
     * named from its regions, and Full Body keeps its focus in the name.
     */
    public function test_workout_names_and_descriptions_name_the_same_day_type(): void
    {
        $this->setBalancedThreeDaySplit([
            ['UPPER_PUSH', 'UPPER_PULL', 'ARMS'],
            ['UPPER_PUSH', 'LOWER'],
            ['UPPER_PULL', 'UPPER_PUSH', 'LOWER'],
        ]);

        $plan = app(WelcomePlanGenerationService::class)->generatePlan($this->makeUser(FitnessGoal::MuscleGain));

        $weekOne = $plan->workoutTemplates->where('week_number', 1)->sortBy('day_of_week')->values();
        $this->assertSame(
            ['Upper Body Day', 'Push & Legs Day', 'Full Body (Pull)'],
            $weekOne->pluck('name')->all()
        );
        foreach (['Upper Body day, 60 min: ', 'Push & Legs day, 60 min: ', 'Full Body day, 60 min: '] as $day => $opening) {
            $this->assertStringStartsWith($opening, $weekOne[$day]->description);
        }

        $this->assertSame(
            ['Upper Body Day (Deload)', 'Push & Legs Day (Deload)', 'Full Body (Pull) (Deload)'],
            $plan->workoutTemplates->where('week_number', 8)->sortBy('day_of_week')->pluck('name')->values()->all()
        );
    }

    /**
     * @param  list<list<string>>  $days
     */
    private function setBalancedThreeDaySplit(array $days): void
    {
        foreach ($days as $dayIndex => $regions) {
            WorkoutSplit::where('days_per_week', 3)
                ->where('focus', SplitFocus::Balanced)
                ->where('day_index', $dayIndex)
                ->update(['target_regions' => $regions]);
        }
    }
}
