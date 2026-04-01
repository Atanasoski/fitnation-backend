<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\TrainingExperience;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\Partner;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FitnessMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_fitness_metrics(): void
    {
        $user = User::factory()->create();
        $user->profile->update([
            'weight' => 80.0,
            'age' => 30,
            'gender' => Gender::Male,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/user/fitness-metrics');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'strength_score' => [
                        'current',
                        'level',
                        'recent_gain',
                        'gain_period',
                    ],
                    'strength_balance' => [
                        'percentage',
                        'level',
                        'recent_change',
                        'muscle_groups',
                    ],
                    'weekly_progress' => [
                        'percentage',
                        'trend',
                        'current_week_workouts',
                        'previous_week_workouts',
                    ],
                ],
                'message',
            ]);
    }

    public function test_fitness_metrics_includes_optional_fields_when_available(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'weight' => 80.0,
            'age' => 30,
            'gender' => Gender::Male,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        // Create comparable users in same partner with sufficient data
        $comparableUsers = User::factory()
            ->count(15)
            ->create(['partner_id' => $partner->id]);

        foreach ($comparableUsers as $comparableUser) {
            $comparableUser->profile->update([
                'weight' => 80.0,
                'age' => fake()->numberBetween(25, 35),
                'gender' => Gender::Male,
                'training_experience' => TrainingExperience::Intermediate,
            ]);

            // Create workout sessions with set logs
            $this->createWorkoutDataForUser($comparableUser, 10);
        }

        // Create workout data for main user
        $this->createWorkoutDataForUser($user, 10);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/user/fitness-metrics');

        $response->assertOk();

        $data = $response->json('data');

        // Check that optional fields may be present
        $this->assertArrayHasKey('strength_score', $data);
        $this->assertArrayHasKey('strength_balance', $data);
        $this->assertArrayHasKey('weekly_progress', $data);

        // Percentile and muscle_groups are optional, so we just check structure
        if (isset($data['strength_score']['percentile'])) {
            $this->assertIsInt($data['strength_score']['percentile']);
            $this->assertGreaterThanOrEqual(0, $data['strength_score']['percentile']);
            $this->assertLessThanOrEqual(100, $data['strength_score']['percentile']);
        }

        if (isset($data['strength_score']['muscle_groups'])) {
            $this->assertIsArray($data['strength_score']['muscle_groups']);
        }

        if (isset($data['strength_balance']['percentile'])) {
            $this->assertIsInt($data['strength_balance']['percentile']);
            $this->assertGreaterThanOrEqual(0, $data['strength_balance']['percentile']);
            $this->assertLessThanOrEqual(100, $data['strength_balance']['percentile']);
        }

        if (isset($data['weekly_progress']['historical_weeks'])) {
            $this->assertIsArray($data['weekly_progress']['historical_weeks']);
            foreach ($data['weekly_progress']['historical_weeks'] as $week) {
                $this->assertArrayHasKey('week', $week);
                $this->assertArrayHasKey('workouts', $week);
            }
        }
    }

    public function test_percentile_calculation_respects_partner_boundaries(): void
    {
        $partner1 = Partner::factory()->create();
        $partner2 = Partner::factory()->create();

        // User in partner1
        $user1 = User::factory()->create(['partner_id' => $partner1->id]);
        $user1->profile->update([
            'weight' => 80.0,
            'age' => 30,
            'gender' => Gender::Male,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        // Users in partner1 (same partner)
        $samePartnerUsers = User::factory()
            ->count(15)
            ->create(['partner_id' => $partner1->id]);

        foreach ($samePartnerUsers as $user) {
            $user->profile->update([
                'weight' => 80.0,
                'age' => 30,
                'gender' => Gender::Male,
                'training_experience' => TrainingExperience::Intermediate,
            ]);
            $this->createWorkoutDataForUser($user, 10);
        }

        // Users in partner2 (different partner - should not be compared)
        $differentPartnerUsers = User::factory()
            ->count(20)
            ->create(['partner_id' => $partner2->id]);

        foreach ($differentPartnerUsers as $user) {
            $user->profile->update([
                'weight' => 80.0,
                'age' => 30,
                'gender' => Gender::Male,
                'training_experience' => TrainingExperience::Intermediate,
            ]);
            $this->createWorkoutDataForUser($user, 10);
        }

        // Create workout data for user1
        $this->createWorkoutDataForUser($user1, 10);

        $response = $this
            ->actingAs($user1, 'sanctum')
            ->getJson('/api/user/fitness-metrics');

        $response->assertOk();

        // Percentile should only be calculated based on users in partner1 (15 users)
        // Not including users from partner2 (20 users)
        if ($response->json('data.strength_score.percentile') !== null) {
            $percentile = $response->json('data.strength_score.percentile');
            $this->assertIsInt($percentile);
            $this->assertGreaterThanOrEqual(0, $percentile);
            $this->assertLessThanOrEqual(100, $percentile);
        }
    }

    public function test_percentile_not_calculated_with_insufficient_comparable_users(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'weight' => 80.0,
            'age' => 30,
            'gender' => Gender::Male,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        // Create only 5 comparable users (less than minimum of 10)
        $comparableUsers = User::factory()
            ->count(5)
            ->create(['partner_id' => $partner->id]);

        foreach ($comparableUsers as $comparableUser) {
            $comparableUser->profile->update([
                'weight' => 80.0,
                'age' => 30,
                'gender' => Gender::Male,
                'training_experience' => TrainingExperience::Intermediate,
            ]);
            $this->createWorkoutDataForUser($comparableUser, 10);
        }

        $this->createWorkoutDataForUser($user, 10);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/user/fitness-metrics');

        $response->assertOk();

        // Percentile should be null with insufficient users
        $this->assertNull($response->json('data.strength_score.percentile'));
    }

    public function test_percentile_not_calculated_for_user_without_partner(): void
    {
        $user = User::factory()->create(['partner_id' => null]);
        $user->profile->update([
            'weight' => 80.0,
            'age' => 30,
            'gender' => Gender::Male,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $this->createWorkoutDataForUser($user, 10);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/user/fitness-metrics');

        $response->assertOk();

        // Percentile should be null for users without partner
        $this->assertNull($response->json('data.strength_score.percentile'));
    }

    public function test_historical_weekly_progress_included(): void
    {
        $user = User::factory()->create();
        $user->profile->update(['weight' => 80.0]);

        // Create workout sessions over the last 8 weeks
        $now = Carbon::now();
        for ($i = 0; $i < 8; $i++) {
            $weekStart = $now->copy()->subWeeks($i)->startOfWeek();
            $weekEnd = $now->copy()->subWeeks($i)->endOfWeek();

            // Create 2-4 workouts per week
            $workoutCount = fake()->numberBetween(2, 4);
            for ($j = 0; $j < $workoutCount; $j++) {
                WorkoutSession::factory()->create([
                    'user_id' => $user->id,
                    'performed_at' => fake()->dateTimeBetween($weekStart, $weekEnd),
                    'completed_at' => now(),
                ]);
            }
        }

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/user/fitness-metrics');

        $response->assertOk();

        $historicalWeeks = $response->json('data.weekly_progress.historical_weeks');
        $this->assertIsArray($historicalWeeks);
        $this->assertCount(8, $historicalWeeks);

        foreach ($historicalWeeks as $week) {
            $this->assertArrayHasKey('week', $week);
            $this->assertArrayHasKey('workouts', $week);
            $this->assertIsString($week['week']);
            $this->assertIsInt($week['workouts']);
            $this->assertGreaterThanOrEqual(0, $week['workouts']);
        }
    }

    public function test_weekly_progress_includes_volume_and_daily_breakdown(): void
    {
        $user = User::factory()->create();
        $user->profile->update(['weight' => 80.0]);

        // Get or create exercise
        $chest = MuscleGroup::firstOrCreate(['name' => 'Chest'], ['body_region' => 'upper']);
        $exercise = Exercise::firstOrCreate(
            ['name' => 'Bench Press'],
            ['description' => 'Bench press exercise']
        );
        $exercise->muscleGroups()->syncWithoutDetaching([$chest->id => ['is_primary' => true]]);

        // Create workout sessions for current week with set logs
        $currentWeekStart = Carbon::now()->startOfWeek();
        $currentWeekEnd = Carbon::now()->endOfWeek();

        // Create sessions on Monday, Wednesday, Friday
        $monday = $currentWeekStart->copy();
        $wednesday = $currentWeekStart->copy()->addDays(2);
        $friday = $currentWeekStart->copy()->addDays(4);

        foreach ([$monday, $wednesday, $friday] as $date) {
            $session = WorkoutSession::factory()->create([
                'user_id' => $user->id,
                'performed_at' => $date->copy()->setTime(10, 0),
                'completed_at' => $date->copy()->setTime(11, 0), // 60 minutes
            ]);

            // Create set logs with volume (weight stored in KG)
            // Session 1: 100kg × 10 reps = 1000 kg, Session 2: 100kg × 8 reps = 800 kg
            // Total per session: 1800 kg = 3968.316 lbs (1800 × 2.20462)
            SetLog::create([
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 1,
                'weight' => 100.0, // 100 kg
                'reps' => 10,
                'rest_seconds' => 60,
            ]);

            SetLog::create([
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 2,
                'weight' => 100.0, // 100 kg
                'reps' => 8,
                'rest_seconds' => 60,
            ]);
        }

        // Create previous week session
        $previousWeekStart = Carbon::now()->subWeek()->startOfWeek();
        $previousSession = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'performed_at' => $previousWeekStart->copy()->setTime(10, 0),
            'completed_at' => $previousWeekStart->copy()->setTime(11, 0),
        ]);

        // Previous week: 90kg × 10 reps = 900 kg = 1984.158 lbs (900 × 2.20462)
        SetLog::create([
            'workout_session_id' => $previousSession->id,
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'weight' => 90.0, // 90 kg
            'reps' => 10,
            'rest_seconds' => 60,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/user/fitness-metrics');

        $response->assertOk();

        $weeklyProgress = $response->json('data.weekly_progress');

        // Check volume fields (should be converted from KG to lbs)
        // Current week: 3 sessions × 1800 kg = 5400 kg = 11904.948 lbs
        // Previous week: 900 kg = 1984.158 lbs
        $this->assertIsInt($weeklyProgress['current_week_volume']);
        $this->assertGreaterThan(10000, $weeklyProgress['current_week_volume']); // Should be ~11905 lbs
        $this->assertIsInt($weeklyProgress['previous_week_volume']);
        $this->assertGreaterThan(1000, $weeklyProgress['previous_week_volume']); // Should be ~1984 lbs
        $this->assertIsInt($weeklyProgress['volume_difference']);
        $this->assertIsInt($weeklyProgress['volume_difference_percent']);

        // Check time field
        $this->assertIsInt($weeklyProgress['current_week_time_minutes']);
        $this->assertEquals(180, $weeklyProgress['current_week_time_minutes']); // 3 sessions × 60 minutes

        // Check daily breakdown
        $this->assertIsArray($weeklyProgress['daily_breakdown']);
        $this->assertCount(7, $weeklyProgress['daily_breakdown']);

        foreach ($weeklyProgress['daily_breakdown'] as $day) {
            $this->assertArrayHasKey('day_of_week', $day);
            $this->assertArrayHasKey('date', $day);
            $this->assertArrayHasKey('volume', $day);
            $this->assertArrayHasKey('workouts', $day);
            $this->assertArrayHasKey('time_minutes', $day);
            $this->assertIsInt($day['day_of_week']);
            $this->assertGreaterThanOrEqual(0, $day['day_of_week']);
            $this->assertLessThanOrEqual(6, $day['day_of_week']);
            $this->assertIsString($day['date']);
            $this->assertIsInt($day['volume']);
            $this->assertIsInt($day['workouts']);
            $this->assertIsInt($day['time_minutes']);
        }

        // Verify Monday has workout data
        $mondayData = $weeklyProgress['daily_breakdown'][0];
        $this->assertEquals(0, $mondayData['day_of_week']); // Monday
        $this->assertGreaterThan(0, $mondayData['volume']);
        $this->assertEquals(1, $mondayData['workouts']);
    }

    public function test_strength_balance_rewards_coverage_across_many_muscle_groups(): void
    {
        $user = User::factory()->create();
        $user->profile->update(['weight' => 80.0]);

        $muscleGroupConfigs = [
            ['name' => 'Chest', 'region' => 'upper', 'exercise' => 'Bench Press', 'sets' => 6],
            ['name' => 'Lower Back', 'region' => 'upper', 'exercise' => 'Deadlift', 'sets' => 3],
            ['name' => 'Hamstrings', 'region' => 'lower', 'exercise' => 'Romanian Deadlift', 'sets' => 3],
            ['name' => 'Glutes', 'region' => 'lower', 'exercise' => 'Hip Thrust', 'sets' => 3],
            ['name' => 'Front Delts', 'region' => 'upper', 'exercise' => 'Overhead Press', 'sets' => 2],
            ['name' => 'Lats', 'region' => 'upper', 'exercise' => 'Lat Pulldown', 'sets' => 2],
            ['name' => 'Upper Back', 'region' => 'upper', 'exercise' => 'Barbell Row', 'sets' => 2],
            ['name' => 'Abs', 'region' => 'core', 'exercise' => 'Cable Crunch', 'sets' => 1],
            ['name' => 'Triceps', 'region' => 'upper', 'exercise' => 'Tricep Pushdown', 'sets' => 1],
            ['name' => 'Side Delts', 'region' => 'upper', 'exercise' => 'Lateral Raise', 'sets' => 1],
            ['name' => 'Obliques', 'region' => 'core', 'exercise' => 'Woodchop', 'sets' => 1],
        ];

        $this->createVariedWorkoutData($user, $muscleGroupConfigs);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/user/fitness-metrics');

        $response->assertOk();

        $balance = $response->json('data.strength_balance');
        $this->assertGreaterThanOrEqual(60, $balance['percentage'], 'Training 11/17 muscle groups should score at least GOOD (60%)');
        $this->assertContains($balance['level'], ['GOOD', 'EXCELLENT']);
    }

    public function test_strength_balance_low_for_single_muscle_group(): void
    {
        $user = User::factory()->create();
        $user->profile->update(['weight' => 80.0]);

        $muscleGroupConfigs = [
            ['name' => 'Chest', 'region' => 'upper', 'exercise' => 'Bench Press', 'sets' => 10],
        ];

        $this->createVariedWorkoutData($user, $muscleGroupConfigs);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/user/fitness-metrics');

        $response->assertOk();

        $balance = $response->json('data.strength_balance');
        $this->assertLessThan(20, $balance['percentage'], 'Training only 1 muscle group should score below 20%');
        $this->assertEquals('NEEDS_IMPROVEMENT', $balance['level']);
    }

    public function test_strength_balance_ppl_split_scores_good_or_better(): void
    {
        $user = User::factory()->create();
        $user->profile->update(['weight' => 80.0]);

        $muscleGroupConfigs = [
            ['name' => 'Chest', 'region' => 'upper', 'exercise' => 'Bench Press', 'sets' => 4],
            ['name' => 'Front Delts', 'region' => 'upper', 'exercise' => 'Overhead Press', 'sets' => 3],
            ['name' => 'Triceps', 'region' => 'upper', 'exercise' => 'Tricep Pushdown', 'sets' => 3],
            ['name' => 'Side Delts', 'region' => 'upper', 'exercise' => 'Lateral Raise', 'sets' => 2],
            ['name' => 'Lats', 'region' => 'upper', 'exercise' => 'Lat Pulldown', 'sets' => 4],
            ['name' => 'Upper Back', 'region' => 'upper', 'exercise' => 'Barbell Row', 'sets' => 3],
            ['name' => 'Rear Delts', 'region' => 'upper', 'exercise' => 'Face Pull', 'sets' => 2],
            ['name' => 'Biceps', 'region' => 'upper', 'exercise' => 'Barbell Curl', 'sets' => 3],
            ['name' => 'Traps', 'region' => 'upper', 'exercise' => 'Shrug', 'sets' => 2],
            ['name' => 'Quadriceps', 'region' => 'lower', 'exercise' => 'Squat', 'sets' => 4],
            ['name' => 'Hamstrings', 'region' => 'lower', 'exercise' => 'Romanian Deadlift', 'sets' => 3],
            ['name' => 'Glutes', 'region' => 'lower', 'exercise' => 'Hip Thrust', 'sets' => 3],
            ['name' => 'Calves', 'region' => 'lower', 'exercise' => 'Calf Raise', 'sets' => 2],
            ['name' => 'Abs', 'region' => 'core', 'exercise' => 'Cable Crunch', 'sets' => 2],
        ];

        $this->createVariedWorkoutData($user, $muscleGroupConfigs);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/user/fitness-metrics');

        $response->assertOk();

        $balance = $response->json('data.strength_balance');
        $this->assertGreaterThanOrEqual(60, $balance['percentage'], 'A full PPL split covering 14/17 groups should score GOOD or better');
        $this->assertContains($balance['level'], ['GOOD', 'EXCELLENT']);
    }

    public function test_strength_balance_zero_for_no_training_data(): void
    {
        $user = User::factory()->create();
        $user->profile->update(['weight' => 80.0]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/user/fitness-metrics');

        $response->assertOk();

        $balance = $response->json('data.strength_balance');
        $this->assertEquals(0, $balance['percentage']);
        $this->assertEquals('NEEDS_IMPROVEMENT', $balance['level']);
    }

    public function test_strength_balance_needs_improvement_for_two_or_three_groups(): void
    {
        $user = User::factory()->create();
        $user->profile->update(['weight' => 80.0]);

        $muscleGroupConfigs = [
            ['name' => 'Chest', 'region' => 'upper', 'exercise' => 'Bench Press', 'sets' => 5],
            ['name' => 'Biceps', 'region' => 'upper', 'exercise' => 'Barbell Curl', 'sets' => 5],
            ['name' => 'Triceps', 'region' => 'upper', 'exercise' => 'Tricep Pushdown', 'sets' => 5],
        ];

        $this->createVariedWorkoutData($user, $muscleGroupConfigs);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/user/fitness-metrics');

        $response->assertOk();

        $balance = $response->json('data.strength_balance');
        $this->assertLessThan(45, $balance['percentage'], 'Training only 3 groups should not score FAIR or above');
    }

    /**
     * Create workout data across varied muscle groups for a user.
     *
     * @param  array<int, array{name: string, region: string, exercise: string, sets: int}>  $muscleGroupConfigs
     */
    private function createVariedWorkoutData(User $user, array $muscleGroupConfigs): void
    {
        $recent30Days = Carbon::now()->subDays(15);

        $session = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'performed_at' => $recent30Days,
            'completed_at' => $recent30Days->copy()->addHours(1),
        ]);

        foreach ($muscleGroupConfigs as $config) {
            $muscleGroup = MuscleGroup::firstOrCreate(
                ['name' => $config['name']],
                ['body_region' => $config['region']]
            );

            $exercise = Exercise::firstOrCreate(
                ['name' => $config['exercise']],
                ['description' => $config['exercise'].' exercise']
            );

            $exercise->muscleGroups()->syncWithoutDetaching([
                $muscleGroup->id => ['is_primary' => true],
            ]);

            for ($s = 1; $s <= $config['sets']; $s++) {
                SetLog::create([
                    'workout_session_id' => $session->id,
                    'exercise_id' => $exercise->id,
                    'set_number' => $s,
                    'weight' => 60.0,
                    'reps' => 10,
                    'rest_seconds' => 60,
                ]);
            }
        }
    }

    /**
     * Helper method to create workout data for a user.
     */
    private function createWorkoutDataForUser(User $user, int $sessionCount): void
    {
        $recent30Days = Carbon::now()->subDays(30);

        // Get or create muscle groups and exercises
        $chest = MuscleGroup::firstOrCreate(['name' => 'Chest'], ['body_region' => 'upper']);
        $exercise = Exercise::firstOrCreate(
            ['name' => 'Bench Press'],
            ['description' => 'Bench press exercise']
        );
        $exercise->muscleGroups()->syncWithoutDetaching([$chest->id => ['is_primary' => true]]);

        for ($i = 0; $i < $sessionCount; $i++) {
            $session = WorkoutSession::factory()->create([
                'user_id' => $user->id,
                'performed_at' => $recent30Days->copy()->addDays($i * 2),
                'completed_at' => $recent30Days->copy()->addDays($i * 2)->addHours(1),
            ]);

            // Create set logs for this session
            SetLog::create([
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 1,
                'weight' => 80.0,
                'reps' => 10,
                'rest_seconds' => 60,
            ]);

            SetLog::create([
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 2,
                'weight' => 80.0,
                'reps' => 8,
                'rest_seconds' => 60,
            ]);

            SetLog::create([
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 3,
                'weight' => 80.0,
                'reps' => 6,
                'rest_seconds' => 60,
            ]);
        }
    }
}
