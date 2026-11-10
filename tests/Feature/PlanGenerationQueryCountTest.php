<?php

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\TrainingExperience;
use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\WelcomePlanGenerationService;
use App\Services\WorkoutGenerator\BlueprintWorkoutGenerator;
use Database\Seeders\WorkoutSplitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesCatalogExercises;
use Tests\TestCase;

/**
 * Guards #124: a plan's days share their constraints, so generating a plan
 * loads the exercise pool once and resolves the member's history for every
 * picked exercise in one batched lookup. The query count is flat in the
 * number of days — no per-day pool load, no per-Slot history lookup.
 */
class PlanGenerationQueryCountTest extends TestCase
{
    use CreatesCatalogExercises;
    use RefreshDatabase;

    /**
     * Seven days of different and repeated day types, so a per-day or
     * per-Slot query would multiply.
     */
    private const SEVEN_DAYS = [
        ['UPPER_PUSH'],
        ['UPPER_PULL'],
        ['LOWER'],
        ['UPPER_PUSH', 'UPPER_PULL'],
        ['ARMS'],
        ['LOWER', 'UPPER_PUSH', 'UPPER_PULL'],
        ['UPPER_PUSH'],
    ];

    public function test_a_seven_day_plan_issues_the_same_queries_as_a_one_day_plan(): void
    {
        $user = $this->memberWithHistory();
        $generator = app(BlueprintWorkoutGenerator::class);
        $preferences = ['duration_minutes' => 90, 'equipment_types' => ['BARBELL', 'DUMBBELL', 'CABLE', 'MACHINE']];

        $oneDay = $this->countQueries(fn () => $generator->generatePlan($user->fresh(), $preferences, [['UPPER_PUSH']]));
        $sevenDays = $this->countQueries(fn () => $generator->generatePlan($user->fresh(), $preferences, self::SEVEN_DAYS));

        $this->assertGreaterThan(0, $oneDay);
        $this->assertSame(
            $oneDay,
            $sevenDays,
            "Plan generation queries scaled with the number of days: {$oneDay} for 1 day, {$sevenDays} for 7. ".
            'The pool must load once per plan and history must resolve in one batched lookup.'
        );
    }

    public function test_program_generation_reads_do_not_grow_with_training_days(): void
    {
        // Writing a program inserts rows per day and week by design; what it
        // reads — the split, the pool, the history, the Deload Week's
        // classification — must not scale with the number of days.
        $this->seed(WorkoutSplitSeeder::class);
        $user = $this->memberWithHistory();
        $user->profile->update([
            'gender' => Gender::Male,
            'workout_duration_minutes' => 60,
        ]);
        $service = app(WelcomePlanGenerationService::class);

        $readsForDays = function (int $trainingDays) use ($user, $service): int {
            $user->profile->update(['training_days_per_week' => $trainingDays]);

            return $this->countQueries(fn () => $service->generatePlan($user->fresh()), readsOnly: true);
        };

        $oneDay = $readsForDays(1);
        $sevenDays = $readsForDays(7);

        $this->assertGreaterThan(0, $oneDay);
        $this->assertSame(
            $oneDay,
            $sevenDays,
            "Program generation reads scaled with training days: {$oneDay} for a 1-day split, {$sevenDays} for 7."
        );
    }

    private function countQueries(callable $generate, bool $readsOnly = false): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $generate();

        $queries = collect(DB::getQueryLog());

        DB::disableQueryLog();
        DB::flushQueryLog();

        if ($readsOnly) {
            $queries = $queries->filter(fn (array $query) => str_starts_with(strtolower(ltrim($query['query'])), 'select'));
        }

        return $queries->count();
    }

    /**
     * A partner member with a full-gym catalog and a completed session
     * logging every exercise in it, so every picked exercise has history.
     */
    private function memberWithHistory(): User
    {
        $partner = Partner::factory()->create();

        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        foreach ([
            ['PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT'],
            ['PRESS', 'UPPER_PUSH', 'DUMBBELL', 'FLAT'],
            ['PRESS', 'UPPER_PUSH', 'BARBELL', 'INCLINE'],
            ['PRESS', 'UPPER_PUSH', 'BARBELL', 'VERTICAL'],
            ['PRESS', 'UPPER_PUSH', 'DUMBBELL', 'VERTICAL'],
            ['SHOULDER_RAISE', 'UPPER_PUSH', 'DUMBBELL', null],
            ['FLY', 'UPPER_PUSH', 'CABLE', 'HORIZONTAL'],
            ['ELBOW_EXTENSION', 'ARMS', 'CABLE', null],
            ['ELBOW_EXTENSION', 'ARMS', 'BARBELL', null],
            ['ELBOW_FLEXION', 'ARMS', 'DUMBBELL', null],
            ['ELBOW_FLEXION', 'ARMS', 'CABLE', null],
            ['PULL_VERTICAL', 'UPPER_PULL', 'CABLE', null],
            ['ROW', 'UPPER_PULL', 'BARBELL', 'HORIZONTAL'],
            ['ROW', 'UPPER_PULL', 'CABLE', 'HORIZONTAL'],
            ['REAR_DELT_FLY', 'UPPER_PULL', 'DUMBBELL', null],
            ['PULLOVER_STRAIGHT_ARM', 'UPPER_PULL', 'CABLE', null],
            ['SQUAT', 'LOWER', 'BARBELL', null],
            ['HINGE', 'LOWER', 'BARBELL', null],
            ['KNEE_EXTENSION', 'LOWER', 'MACHINE', null],
            ['KNEE_FLEXION', 'LOWER', 'MACHINE', null],
            ['CALF_RAISE', 'LOWER', 'MACHINE', null],
            ['ROTATION', 'CORE', 'CABLE', null],
        ] as [$pattern, $region, $equipment, $angle]) {
            $this->catalogExercise($pattern, $region, $equipment, $angle)
                ->partners()->attach($partner->id);
        }

        $session = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Completed,
            'completed_at' => now()->subDay(),
        ]);

        foreach (Exercise::all() as $exercise) {
            foreach ([1, 2, 3] as $setNumber) {
                SetLog::create([
                    'workout_session_id' => $session->id,
                    'exercise_id' => $exercise->id,
                    'set_number' => $setNumber,
                    'weight' => 40,
                    'reps' => 10,
                    'rest_seconds' => 120,
                ]);
            }
        }

        return $user;
    }
}
