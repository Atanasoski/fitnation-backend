<?php

namespace Tests\Unit\Services;

use App\Enums\Gender;
use App\Enums\TrainingExperience;
use App\Enums\WorkoutSessionStatus;
use App\Models\Angle;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\WorkoutGenerator\ProgressionCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressionCalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProgressionCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProgressionCalculatorService;
    }

    public function test_returns_zero_weight_when_user_has_no_profile(): void
    {
        $user = User::factory()->create();
        // Delete any auto-created profile
        $user->profile()->delete();

        $exercise = $this->createExercise('SQUAT', 'BARBELL');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Beginner);

        $this->assertEquals(0, $targets['target_weight']);
    }

    public function test_returns_zero_weight_when_profile_has_no_body_weight(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => null,
        ]);

        $exercise = $this->createExercise('SQUAT', 'BARBELL');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Beginner);

        $this->assertEquals(0, $targets['target_weight']);
    }

    public function test_estimates_squat_weight_for_beginner_male(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 80,
            'gender' => Gender::Male,
        ]);

        $exercise = $this->createExercise('SQUAT', 'BARBELL');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Beginner);

        // 80 × 1.0 (squat) × 1.0 (male) × 0.6 (beginner) × 1.0 (barbell) × 1.0 (no angle mod for squat) = 48
        // Barbell rounds to 2.5kg = 47.5kg
        $this->assertEquals(47.5, $targets['target_weight']);
    }

    public function test_estimates_squat_weight_for_intermediate_male(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 80,
            'gender' => Gender::Male,
        ]);

        $exercise = $this->createExercise('SQUAT', 'BARBELL');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Intermediate);

        // 80 × 1.0 × 1.0 × 1.0 × 1.0 × 1.0 = 80kg
        $this->assertEquals(80.0, $targets['target_weight']);
    }

    public function test_estimates_squat_weight_for_advanced_female(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 60,
            'gender' => Gender::Female,
        ]);

        $exercise = $this->createExercise('SQUAT', 'BARBELL');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Advanced);

        // 60 × 1.0 (squat) × 0.65 (female) × 1.3 (advanced) × 1.0 (barbell) = 50.7
        // Barbell rounds to 2.5kg = 50kg
        $this->assertEquals(50.0, $targets['target_weight']);
    }

    public function test_estimates_bench_press_weight_for_beginner_male(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 80,
            'gender' => Gender::Male,
        ]);

        $exercise = $this->createExercise('PRESS', 'BARBELL', 'FLAT');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Beginner);

        // 80 × 0.65 (press) × 1.0 (male) × 0.6 (beginner) × 1.0 (barbell) × 1.0 (flat) = 31.2
        // Barbell rounds to 2.5kg = 30kg
        $this->assertEquals(30.0, $targets['target_weight']);
    }

    public function test_estimates_overhead_press_lighter_than_bench_press(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 80,
            'gender' => Gender::Male,
        ]);

        $benchPress = $this->createExercise('PRESS', 'BARBELL', 'FLAT');
        $overheadPress = $this->createExercise('PRESS', 'BARBELL', 'VERTICAL');

        $benchTargets = $this->service->calculateTargets($benchPress, $user->fresh(), TrainingExperience::Intermediate);
        $ohpTargets = $this->service->calculateTargets($overheadPress, $user->fresh(), TrainingExperience::Intermediate);

        // Bench: 80 × 0.65 × 1.0 × 1.0 × 1.0 × 1.0 = 52 → 52.5
        // OHP:   80 × 0.65 × 1.0 × 1.0 × 1.0 × 0.65 = 33.8 → 35.0
        $this->assertEquals(52.5, $benchTargets['target_weight']);
        $this->assertEquals(35.0, $ohpTargets['target_weight']);
        $this->assertGreaterThan($ohpTargets['target_weight'], $benchTargets['target_weight']);
    }

    public function test_estimates_dumbbell_press_per_dumbbell(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 80,
            'gender' => Gender::Male,
        ]);

        $barbellPress = $this->createExercise('PRESS', 'BARBELL', 'FLAT');
        $dumbbellPress = $this->createExercise('PRESS', 'DUMBBELL', 'FLAT');

        $barbellTargets = $this->service->calculateTargets($barbellPress, $user->fresh(), TrainingExperience::Intermediate);
        $dumbbellTargets = $this->service->calculateTargets($dumbbellPress, $user->fresh(), TrainingExperience::Intermediate);

        // Barbell: 80 × 0.65 × 1.0 × 1.0 × 1.0 × 1.0 = 52 → 52.5 (total)
        // Dumbbell: 80 × 0.65 × 1.0 × 1.0 × 0.45 × 1.0 = 23.4 → 24.0 (per dumbbell)
        $this->assertEquals(52.5, $barbellTargets['target_weight']);
        $this->assertEquals(24.0, $dumbbellTargets['target_weight']);
        // Dumbbell per hand should be roughly half of barbell total
        $this->assertLessThan($barbellTargets['target_weight'] * 0.55, $dumbbellTargets['target_weight']);
    }

    public function test_estimates_bicep_curl_weight_for_intermediate_male(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 80,
            'gender' => Gender::Male,
        ]);

        $exercise = $this->createExercise('ELBOW_FLEXION', 'DUMBBELL');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Intermediate);

        // 80 × 0.15 (bicep) × 1.0 (male) × 1.0 (intermediate) × 0.45 (dumbbell) × 1.0 (no angle mod) = 5.4
        // Dumbbell rounds to 2kg = 6kg per dumbbell
        $this->assertEquals(6.0, $targets['target_weight']);
    }

    public function test_returns_zero_for_bodyweight_exercises(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 80,
            'gender' => Gender::Male,
        ]);

        $exercise = $this->createExercise('PUSHUP', 'BODYWEIGHT');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Beginner);

        // Bodyweight exercises should return 0
        $this->assertEquals(0, $targets['target_weight']);
    }

    public function test_returns_zero_for_trx_exercises(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 80,
            'gender' => Gender::Male,
        ]);

        $exercise = $this->createExercise('PUSHUP', 'TRX');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Beginner);

        // TRX exercises should return 0 weight
        $this->assertEquals(0, $targets['target_weight']);
    }

    public function test_uses_correct_rounding_for_machine_exercises(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 77,
            'gender' => Gender::Male,
        ]);

        $exercise = $this->createExercise('LEG_PRESS', 'MACHINE');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Beginner);

        // 77 × 1.0 (leg press) × 1.0 (male) × 0.6 (beginner) × 0.80 (machine) × 1.0 = 36.96
        // Machine rounds to 5kg = 35kg
        $this->assertEquals(35.0, $targets['target_weight']);
    }

    public function test_uses_correct_rounding_for_barbell_exercises(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 83,
            'gender' => Gender::Male,
        ]);

        $exercise = $this->createExercise('ROW', 'BARBELL', 'HORIZONTAL');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Beginner);

        // 83 × 0.55 (row) × 1.0 (male) × 0.6 (beginner) × 1.0 (barbell) × 1.0 (horizontal) = 27.39
        // Barbell rounds to 2.5kg = 27.5kg
        $this->assertEquals(27.5, $targets['target_weight']);
    }

    public function test_estimates_correctly_for_gender_other(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 70,
            'gender' => Gender::Other,
        ]);

        $exercise = $this->createExercise('SQUAT', 'BARBELL');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Beginner);

        // 70 × 1.0 × 0.80 (other) × 0.6 × 1.0 × 1.0 = 33.6
        // Rounded to nearest 2.5kg (barbell) = 32.5kg
        $this->assertEquals(32.5, $targets['target_weight']);
    }

    public function test_uses_barbell_rounding_increment(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 80,
            'gender' => Gender::Male,
        ]);

        $exercise = $this->createExercise('PRESS', 'BARBELL', 'FLAT');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Intermediate);

        // 80 × 0.65 × 1.0 × 1.0 × 1.0 (barbell) × 1.0 (flat) = 52
        // Barbell rounds to 2.5kg increments = 52.5kg
        $this->assertEquals(52.5, $targets['target_weight']);
    }

    public function test_uses_dumbbell_rounding_increment(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 80,
            'gender' => Gender::Male,
        ]);

        $exercise = $this->createExercise('PRESS', 'DUMBBELL', 'FLAT');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Intermediate);

        // 80 × 0.65 × 1.0 × 1.0 × 0.45 (dumbbell) × 1.0 (flat) = 23.4
        // Dumbbell rounds to 2kg increments = 24kg per dumbbell
        $this->assertEquals(24.0, $targets['target_weight']);
    }

    public function test_uses_machine_rounding_increment(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 80,
            'gender' => Gender::Male,
        ]);

        $exercise = $this->createExercise('KNEE_EXTENSION', 'MACHINE');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Intermediate);

        // 80 × 0.35 × 1.0 × 1.0 × 0.80 (machine) × 1.0 = 22.4
        // Machine rounds to 5kg increments = 20kg
        $this->assertEquals(20.0, $targets['target_weight']);
    }

    public function test_uses_kettlebell_rounding_increment(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 80,
            'gender' => Gender::Male,
        ]);

        $exercise = $this->createExercise('HINGE', 'KETTLEBELL');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Beginner);

        // 80 × 1.1 (hinge) × 1.0 × 0.6 × 0.45 (kettlebell) × 1.0 = 23.76
        // Kettlebell rounds to 4kg increments = 24kg
        $this->assertEquals(24.0, $targets['target_weight']);
    }

    public function test_uses_cable_rounding_increment(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 80,
            'gender' => Gender::Male,
        ]);

        $exercise = $this->createExercise('ELBOW_FLEXION', 'CABLE');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Intermediate);

        // 80 × 0.15 (bicep) × 1.0 × 1.0 × 0.55 (cable) × 1.0 = 6.6
        // Cable rounds to 2.5kg increments = 7.5kg
        $this->assertEquals(7.5, $targets['target_weight']);
    }

    public function test_landmine_press_weight_is_moderate(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 80,
            'gender' => Gender::Male,
        ]);

        $exercise = $this->createExercise('PRESS', 'BARBELL', 'LOW_TO_HIGH');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Intermediate);

        // 80 × 0.65 × 1.0 × 1.0 × 1.0 (barbell) × 0.60 (low-to-high) = 31.2
        // Barbell rounds to 2.5kg = 30.0kg (realistic for landmine press)
        $this->assertEquals(30.0, $targets['target_weight']);
    }

    public function test_cable_vertical_press_weight_is_light(): void
    {
        $user = User::factory()->create();
        $user->profile()->delete();
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'weight' => 80,
            'gender' => Gender::Male,
        ]);

        $exercise = $this->createExercise('PRESS', 'CABLE', 'VERTICAL');

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Intermediate);

        // 80 × 0.65 × 1.0 × 1.0 × 0.55 (cable) × 0.65 (vertical) = 18.59
        // Cable rounds to 2.5kg = 17.5kg (realistic for cable front raises)
        $this->assertEquals(17.5, $targets['target_weight']);
    }

    public function test_progressive_overload_uses_equipment_rounding(): void
    {
        $user = User::factory()->create();
        $exercise = $this->createExercise('PRESS', 'BARBELL', 'FLAT');

        $session = \App\Models\WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Completed,
            'completed_at' => now(),
        ]);

        \App\Models\SetLog::insert([
            [
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 1,
                'weight' => 50,
                'reps' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 2,
                'weight' => 50,
                'reps' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 3,
                'weight' => 50,
                'reps' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Intermediate);

        $this->assertEquals(52.5, $targets['target_weight']);
    }

    public function test_progressive_overload_uses_machine_rounding(): void
    {
        $user = User::factory()->create();
        $exercise = $this->createExercise('KNEE_EXTENSION', 'MACHINE');

        $session = \App\Models\WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Completed,
            'completed_at' => now(),
        ]);

        \App\Models\SetLog::insert([
            [
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 1,
                'weight' => 45,
                'reps' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 2,
                'weight' => 45,
                'reps' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 3,
                'weight' => 45,
                'reps' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Intermediate);

        $this->assertEquals(50.0, $targets['target_weight']);
    }

    public function test_progressive_overload_uses_dumbbell_rounding(): void
    {
        $user = User::factory()->create();
        $exercise = $this->createExercise('PRESS', 'DUMBBELL', 'FLAT');

        $session = \App\Models\WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Completed,
            'completed_at' => now(),
        ]);

        \App\Models\SetLog::insert([
            [
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 1,
                'weight' => 24,
                'reps' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 2,
                'weight' => 24,
                'reps' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 3,
                'weight' => 24,
                'reps' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Beginner);

        $this->assertEquals(26.0, $targets['target_weight']);
    }

    public function test_progressive_overload_keeps_weight_when_not_all_sets_hit_max_reps(): void
    {
        $user = User::factory()->create();
        $exercise = $this->createExercise('PRESS', 'BARBELL', 'FLAT');

        $session = \App\Models\WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Completed,
            'completed_at' => now(),
        ]);

        \App\Models\SetLog::insert([
            [
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 1,
                'weight' => 50,
                'reps' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 2,
                'weight' => 50,
                'reps' => 11,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Intermediate);

        $this->assertEquals(50.0, $targets['target_weight']);
    }

    public function test_progressive_overload_keeps_mode_weight_when_user_drops_load_in_later_sets(): void
    {
        $user = User::factory()->create();
        $exercise = $this->createExercise('PRESS', 'BARBELL', 'FLAT');

        $session = \App\Models\WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Completed,
            'completed_at' => now(),
        ]);

        \App\Models\SetLog::insert([
            [
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 1,
                'weight' => 50,
                'reps' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 2,
                'weight' => 50,
                'reps' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 3,
                'weight' => 45,
                'reps' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $targets = $this->service->calculateTargets($exercise, $user->fresh(), TrainingExperience::Intermediate);

        $this->assertEquals(50.0, $targets['target_weight']);
    }

    public function test_progression_status_is_no_history_when_no_last_performance_exists(): void
    {
        $status = $this->service->getProgressionStatus(null, 8, 12);

        $this->assertSame('no_history', $status);
    }

    public function test_progression_status_is_ready_when_all_sets_hit_max_reps_at_same_weight(): void
    {
        $lastPerformance = [
            'reps' => [12, 12, 12],
            'weights' => [50.0, 50.0, 50.0],
        ];

        $status = $this->service->getProgressionStatus($lastPerformance, 8, 12);

        $this->assertSame('ready', $status);
    }

    public function test_progression_status_is_below_min_when_any_set_is_under_min_reps(): void
    {
        $lastPerformance = [
            'reps' => [12, 6, 10],
            'weights' => [50.0, 50.0, 50.0],
        ];

        $status = $this->service->getProgressionStatus($lastPerformance, 8, 12);

        $this->assertSame('below_min', $status);
    }

    public function test_progression_status_is_working_when_sets_meet_min_but_not_ready_to_progress(): void
    {
        $lastPerformance = [
            'reps' => [10, 11, 9],
            'weights' => [50.0, 50.0, 50.0],
        ];

        $status = $this->service->getProgressionStatus($lastPerformance, 8, 12);

        $this->assertSame('working', $status);
    }

    private function createExercise(string $movementCode, string $equipmentCode = 'BARBELL', ?string $angleCode = null): Exercise
    {
        $movementPattern = MovementPattern::firstOrCreate(
            ['code' => $movementCode],
            ['name' => $movementCode, 'display_order' => 1]
        );

        $equipmentType = EquipmentType::firstOrCreate(
            ['code' => $equipmentCode],
            ['name' => $equipmentCode, 'display_order' => 1]
        );

        $angleId = null;
        if ($angleCode) {
            $angle = Angle::firstOrCreate(
                ['code' => $angleCode],
                ['name' => $angleCode, 'display_order' => 1]
            );
            $angleId = $angle->id;
        }

        return Exercise::factory()->create([
            'movement_pattern_id' => $movementPattern->id,
            'equipment_type_id' => $equipmentType->id,
            'angle_id' => $angleId,
        ]);
    }
}
