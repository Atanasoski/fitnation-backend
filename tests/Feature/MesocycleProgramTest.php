<?php

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\TrainingExperience;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\Partner;
use App\Models\TargetRegion;
use App\Models\User;
use App\Services\WelcomePlanGenerationService;
use App\Services\WorkoutGenerator\DeterministicWorkoutGenerator;
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

            $weekOne = $plan->workoutTemplates
                ->where('week_number', 1)
                ->where('day_of_week', $template->day_of_week)
                ->first();

            // Same exercises, same order as the accumulation weeks.
            $this->assertSame(
                $weekOne->workoutTemplateExercises->sortBy('order')->pluck('exercise_id')->values()->all(),
                $template->workoutTemplateExercises->sortBy('order')->pluck('exercise_id')->values()->all()
            );

            foreach ($template->workoutTemplateExercises as $row) {
                $this->assertSame(2, $row->target_sets);

                $isCompound = DeterministicWorkoutGenerator::isCompoundPattern(
                    $row->exercise->movementPattern?->code
                );

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
}
