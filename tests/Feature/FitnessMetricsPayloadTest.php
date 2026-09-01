<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\TrainingExperience;
use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The payload lock for GET /api/user/fitness-metrics.
 *
 * Time is frozen and the fixture is fully determined, so this asserts the whole
 * response body rather than its shape. It exists to hold the endpoint still
 * while the calculations behind it move into app/Services/FitnessMetrics — any
 * diff here is a behaviour change, not a refactor.
 */
class FitnessMetricsPayloadTest extends TestCase
{
    use RefreshDatabase;

    /** Wednesday. Fixed so week bounds and "M d" labels are deterministic. */
    private const NOW = '2026-03-11 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::NOW));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_fitness_metrics_payload_is_unchanged_for_a_fixed_fixture(): void
    {
        $user = $this->userWithFixedTrainingHistory();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/user/fitness-metrics');

        $response->assertOk();

        $this->assertSame([
            'success' => true,
            'data' => [
                'strength_score' => [
                    // Best 1RM per exercise over the last 30 days, summed and
                    // divided by an 80kg body weight, × 100.
                    // Bench 100×(1+10/30)=133.33, Row 80×(1+10/30)=106.67,
                    // Squat 120×(1+8/30)=152 -> 392 / 80 × 100 = 490.
                    'current' => 490,
                    'level' => 'ADVANCED',
                    // Nothing in the 30-60 day window, so the whole score is gain.
                    'recent_gain' => 490,
                    'gain_period' => 'last_30_days',
                    // No partner -> no cohort -> no percentile key at all.
                    'muscle_groups' => [
                        'chest' => 167,
                        'back' => 133,
                        'legs' => 190,
                    ],
                ],
                'strength_balance' => [
                    // Volume share of the last 30 days: chest 4800, upper back
                    // 2400, quads 8640 of 15840. Coverage 3/17 × evenness 0.887,
                    // geometric mean -> 40.
                    'percentage' => 40,
                    'level' => 'NEEDS_IMPROVEMENT',
                    // Nothing in the 30-60 day window, so the whole balance is change.
                    'recent_change' => 40,
                    // Reported in tracked order, trained or not.
                    'muscle_groups' => [
                        'chest' => 30,
                        'lats' => 0,
                        'upper back' => 15,
                        'lower back' => 0,
                        'front delts' => 0,
                        'side delts' => 0,
                        'rear delts' => 0,
                        'traps' => 0,
                        'biceps' => 0,
                        'triceps' => 0,
                        'forearms' => 0,
                        'quadriceps' => 55,
                        'hamstrings' => 0,
                        'glutes' => 0,
                        'calves' => 0,
                        'abs' => 0,
                        'obliques' => 0,
                    ],
                ],
                'weekly_progress' => [
                    'percentage' => 100,
                    'trend' => 'up',
                    'current_week_workouts' => 2,
                    'previous_week_workouts' => 1,
                    'current_week_volume' => 12960,
                    'previous_week_volume' => 2880,
                    'volume_difference' => 10080,
                    'volume_difference_percent' => 350,
                    'current_week_time_minutes' => 120,
                    'daily_breakdown' => [
                        ['day_of_week' => 0, 'date' => '2026-03-02', 'volume' => 8280, 'workouts' => 1, 'time_minutes' => 60],
                        ['day_of_week' => 1, 'date' => '2026-03-03', 'volume' => 0, 'workouts' => 0, 'time_minutes' => 0],
                        ['day_of_week' => 2, 'date' => '2026-03-04', 'volume' => 4680, 'workouts' => 1, 'time_minutes' => 60],
                        ['day_of_week' => 3, 'date' => '2026-03-05', 'volume' => 0, 'workouts' => 0, 'time_minutes' => 0],
                        ['day_of_week' => 4, 'date' => '2026-03-06', 'volume' => 0, 'workouts' => 0, 'time_minutes' => 0],
                        ['day_of_week' => 5, 'date' => '2026-03-07', 'volume' => 0, 'workouts' => 0, 'time_minutes' => 0],
                        ['day_of_week' => 6, 'date' => '2026-03-08', 'volume' => 0, 'workouts' => 0, 'time_minutes' => 0],
                    ],
                    'historical_weeks' => [
                        ['week' => 'Jan 19', 'workouts' => 0],
                        ['week' => 'Jan 26', 'workouts' => 0],
                        ['week' => 'Feb 02', 'workouts' => 0],
                        ['week' => 'Feb 09', 'workouts' => 0],
                        ['week' => 'Feb 16', 'workouts' => 0],
                        ['week' => 'Feb 23', 'workouts' => 1],
                        ['week' => 'Mar 02', 'workouts' => 2],
                        ['week' => 'Mar 09', 'workouts' => 0],
                    ],
                ],
            ],
            'message' => 'Fitness metrics retrieved successfully',
        ], $response->json());
    }

    /**
     * Three completed sessions across two weeks, hitting three muscle groups.
     *
     * Every session sets both `status` and `completed_at`, so the fixture reads
     * the same under either definition of a completed session — that is
     * deliberate, so this test stays a payload lock and does not double as the
     * test for which predicate the service uses. That one is
     * {@see FitnessMetricsCompletedSessionTest}.
     */
    private function userWithFixedTrainingHistory(): User
    {
        $user = User::factory()->create(['partner_id' => null]);
        $user->profile->update([
            'weight' => 80.0,
            'age' => 30,
            'gender' => Gender::Male,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $bench = $this->exerciseFor('Bench Press', 'Chest', 'upper');
        $row = $this->exerciseFor('Barbell Row', 'Upper Back', 'upper');
        $squat = $this->exerciseFor('Squat', 'Quadriceps', 'lower');

        // The week getWeeklyProgress calls "current": the last full week.
        $monday = Carbon::parse('2026-03-02 10:00:00');
        $wednesday = Carbon::parse('2026-03-04 10:00:00');
        // The comparison week.
        $previousMonday = Carbon::parse('2026-02-23 10:00:00');

        // Three sets of everything: a muscle group needs at least three logged
        // sets in the window before it earns a strength score of its own.
        $this->sessionWith($user, $monday, [
            [$squat, 120.0, 8, 3],   // 3 × 960  = 2880
            [$bench, 100.0, 10, 3],  // 3 × 1000 = 3000
            [$row, 80.0, 10, 3],     // 3 × 800  = 2400  -> 8280
        ]);

        $this->sessionWith($user, $wednesday, [
            [$squat, 120.0, 8, 3],   // 2880
            [$bench, 60.0, 10, 3],   // 1800            -> 4680
        ]);

        $this->sessionWith($user, $previousMonday, [
            [$squat, 120.0, 8, 3],   // 2880
        ]);

        return $user;
    }

    /**
     * @param  array<int, array{0: Exercise, 1: float, 2: int, 3: int}>  $sets
     */
    private function sessionWith(User $user, Carbon $performedAt, array $sets): void
    {
        $session = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Completed,
            'performed_at' => $performedAt,
            'completed_at' => $performedAt->copy()->addHour(),
            'notes' => null,
        ]);

        $setNumber = 1;

        foreach ($sets as [$exercise, $weight, $reps, $count]) {
            for ($i = 0; $i < $count; $i++) {
                SetLog::create([
                    'workout_session_id' => $session->id,
                    'exercise_id' => $exercise->id,
                    'set_number' => $setNumber++,
                    'weight' => $weight,
                    'reps' => $reps,
                    'rest_seconds' => 60,
                ]);
            }
        }
    }

    private function exerciseFor(string $exerciseName, string $muscleGroupName, string $bodyRegion): Exercise
    {
        $muscleGroup = MuscleGroup::firstOrCreate(
            ['name' => $muscleGroupName],
            ['body_region' => $bodyRegion]
        );

        $exercise = Exercise::firstOrCreate(
            ['name' => $exerciseName],
            ['description' => $exerciseName.' exercise']
        );

        $exercise->muscleGroups()->syncWithoutDetaching([
            $muscleGroup->id => ['is_primary' => true],
        ]);

        return $exercise;
    }
}
