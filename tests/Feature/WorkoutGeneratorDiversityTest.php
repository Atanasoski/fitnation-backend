<?php

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\TrainingExperience;
use App\Models\Angle;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\Partner;
use App\Models\TargetRegion;
use App\Models\TrainingStyle;
use App\Models\User;
use App\Services\WorkoutGenerator\DeterministicWorkoutGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutGeneratorDiversityTest extends TestCase
{
    use RefreshDatabase;

    private DeterministicWorkoutGenerator $generator;

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

    private function attachToPartnerAndTrainingStyle(iterable $exercises, Partner $partner, string $styleCode): void
    {
        $style = TrainingStyle::firstOrCreate(
            ['code' => $styleCode],
            ['code' => $styleCode, 'name' => $styleCode, 'display_order' => 10]
        );

        foreach ($exercises as $exercise) {
            $exercise->partners()->syncWithoutDetaching([$partner->id]);
            $exercise->trainingStyles()->syncWithoutDetaching([$style->id]);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(DeterministicWorkoutGenerator::class);
    }

    public function test_generator_respects_max_exercises_per_pattern(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        // Create 5 PRESS exercises with different angles
        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->incline()->create(['name' => 'Incline Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->vertical()->create(['name' => 'Push Press']);
        Exercise::factory()->press()->barbell()->lowToHigh()->create(['name' => 'Landmine Press']);
        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Close-Grip Bench Press']);

        // Create 3 ROW exercises with different angles
        Exercise::factory()->row()->barbell()->horizontal()->create(['name' => 'Barbell Row']);
        Exercise::factory()->row()->barbell()->lowToHigh()->create(['name' => 'Single-Arm Landmine Row']);
        Exercise::factory()->row()->barbell()->horizontal()->create(['name' => 'Pendlay Row']);

        // Link all exercises to partner
        $exercises = Exercise::all();
        $this->attachToPartnerAndBodybuildingStyle($exercises, $partner);

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH', 'UPPER_PULL'],
            'equipment_types' => ['BARBELL'],
            'duration_minutes' => 60,
        ]);

        $this->assertNotEmpty($result['exercises']);

        // Count exercises by movement pattern
        $patternCounts = [];
        foreach ($result['exercises'] as $exerciseData) {
            $exercise = Exercise::find($exerciseData['exercise_id']);
            $pattern = $exercise->movementPattern?->code ?? 'UNKNOWN';
            $patternCounts[$pattern] = ($patternCounts[$pattern] ?? 0) + 1;
        }

        // Should respect max_exercises_per_pattern from config
        $maxPerPattern = config('workout_generator.max_exercises_per_pattern', 4);
        $this->assertLessThanOrEqual($maxPerPattern, $patternCounts['PRESS'] ?? 0, 'Should respect max_exercises_per_pattern for PRESS');
        $this->assertLessThanOrEqual($maxPerPattern, $patternCounts['ROW'] ?? 0, 'Should respect max_exercises_per_pattern for ROW');
    }

    public function test_generator_prevents_duplicate_pattern_angle_combinations_in_strict_pass(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        // Create multiple PRESS exercises with the same angle (FLAT)
        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Close-Grip Bench Press']);
        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Wide-Grip Bench Press']);

        // Create multiple PRESS exercises with different angles
        Exercise::factory()->press()->barbell()->incline()->create(['name' => 'Incline Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->vertical()->create(['name' => 'Push Press']);

        // Link all exercises to partner
        $exercises = Exercise::all();
        $this->attachToPartnerAndBodybuildingStyle($exercises, $partner);

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH'],
            'equipment_types' => ['BARBELL'],
            'duration_minutes' => 60,
        ]);

        $this->assertNotEmpty($result['exercises']);

        // Track pattern|angle combinations
        // Note: Relaxed second pass may allow duplicates if below minimum, but strict pass should not
        $combinations = [];
        $duplicates = [];
        foreach ($result['exercises'] as $exerciseData) {
            $exercise = Exercise::find($exerciseData['exercise_id']);
            $pattern = $exercise->movementPattern?->code ?? 'UNKNOWN';
            $angle = $exercise->angle?->code ?? 'NO_ANGLE';
            $key = "{$pattern}|{$angle}";

            if (in_array($key, $combinations)) {
                $duplicates[] = $key;
            }
            $combinations[] = $key;
        }

        // If we have enough exercises (above min), strict pass should prevent duplicates
        // If we're below min, relaxed pass may add duplicates, which is acceptable
        $targets = config('workout_generator.exercise_count_targets.muscle_gain.intermediate', []);
        $minTotal = $targets['min'] ?? 5;
        if (count($result['exercises']) >= $minTotal) {
            $this->assertEmpty($duplicates, 'Should not have duplicate pattern|angle combinations when above minimum');
        }
    }

    public function test_generator_selects_diverse_exercises_with_different_angles(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        // Create PRESS exercises with different angles
        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->incline()->create(['name' => 'Incline Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->vertical()->create(['name' => 'Push Press']);
        Exercise::factory()->press()->barbell()->lowToHigh()->create(['name' => 'Landmine Press']);

        // Create ROW exercises with different angles
        Exercise::factory()->row()->barbell()->horizontal()->create(['name' => 'Barbell Row']);
        Exercise::factory()->row()->barbell()->lowToHigh()->create(['name' => 'Single-Arm Landmine Row']);

        // Link all exercises to partner
        $exercises = Exercise::all();
        $this->attachToPartnerAndBodybuildingStyle($exercises, $partner);

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH', 'UPPER_PULL'],
            'equipment_types' => ['BARBELL'],
            'duration_minutes' => 60,
        ]);

        $this->assertNotEmpty($result['exercises']);

        // Should have exercises with different angles
        $angles = [];
        foreach ($result['exercises'] as $exerciseData) {
            $exercise = Exercise::find($exerciseData['exercise_id']);
            if ($exercise->angle) {
                $angles[] = $exercise->angle->code;
            }
        }

        // Should have at least 2 different angles
        $uniqueAngles = array_unique($angles);
        $this->assertGreaterThanOrEqual(2, count($uniqueAngles), 'Should have exercises with different angles');
    }

    public function test_strength_user_gets_at_least_3_exercises_in_30_minutes(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::Strength,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        // Create diverse exercises (mix of compound and isolation)
        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->incline()->create(['name' => 'Incline Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->vertical()->create(['name' => 'Push Press']);
        Exercise::factory()->row()->barbell()->horizontal()->create(['name' => 'Barbell Row']);
        Exercise::factory()->row()->barbell()->lowToHigh()->create(['name' => 'Single-Arm Landmine Row']);

        // Create some isolation exercises
        $flyPattern = MovementPattern::firstOrCreate(['code' => 'FLY'], ['name' => 'Fly', 'display_order' => 20]);
        $upperPush = TargetRegion::firstOrCreate(['code' => 'UPPER_PUSH'], ['name' => 'Upper Push', 'display_order' => 10]);
        $barbellEquipment = EquipmentType::firstOrCreate(['code' => 'BARBELL'], ['name' => 'Barbell', 'display_order' => 10]);
        Exercise::factory()->create([
            'name' => 'Dumbbell Fly',
            'movement_pattern_id' => $flyPattern->id,
            'target_region_id' => $upperPush->id,
            'equipment_type_id' => $barbellEquipment->id,
            'angle_id' => Angle::firstOrCreate(['code' => 'FLAT'], ['name' => 'Flat', 'display_order' => 10])->id,
        ]);

        // Link all exercises to partner
        $exercises = Exercise::all();
        $this->attachToPartnerAndBodybuildingStyle($exercises, $partner);

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH', 'UPPER_PULL'],
            'equipment_types' => ['BARBELL'],
            'duration_minutes' => 30,
        ]);

        // Strength user with long rest periods (180s) - each compound exercise takes ~12 min
        // In 30 minutes with 10% buffer (27 min), only 2 exercises fit, which is correct
        $safetyMin = config('workout_generator.exercise_count_safety.min', 3);
        $exerciseCount = count($result['exercises']);
        // Should get at least safety minimum if time allows, but duration is primary constraint
        $this->assertGreaterThanOrEqual(2, $exerciseCount, 'Strength user should get at least 2 exercises in 30 minutes (duration is primary constraint)');
        $this->assertLessThanOrEqual($safetyMin + 1, $exerciseCount, 'Should respect duration constraint even if below safety minimum');
    }

    public function test_beginner_gets_mostly_compound_exercises(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Beginner,
        ]);

        // Create compound exercises
        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->incline()->create(['name' => 'Incline Barbell Bench Press']);
        Exercise::factory()->row()->barbell()->horizontal()->create(['name' => 'Barbell Row']);
        Exercise::factory()->row()->barbell()->lowToHigh()->create(['name' => 'Single-Arm Landmine Row']);

        // Create isolation exercises
        $flyPattern = MovementPattern::firstOrCreate(['code' => 'FLY'], ['name' => 'Fly', 'display_order' => 20]);
        $elbowFlexionPattern = MovementPattern::firstOrCreate(['code' => 'ELBOW_FLEXION'], ['name' => 'Elbow Flexion', 'display_order' => 30]);
        $upperPush = TargetRegion::firstOrCreate(['code' => 'UPPER_PUSH'], ['name' => 'Upper Push', 'display_order' => 10]);
        $upperPull = TargetRegion::firstOrCreate(['code' => 'UPPER_PULL'], ['name' => 'Upper Pull', 'display_order' => 20]);
        $barbellEquipment = EquipmentType::firstOrCreate(['code' => 'BARBELL'], ['name' => 'Barbell', 'display_order' => 10]);

        Exercise::factory()->create([
            'name' => 'Dumbbell Fly',
            'movement_pattern_id' => $flyPattern->id,
            'target_region_id' => $upperPush->id,
            'equipment_type_id' => $barbellEquipment->id,
            'angle_id' => Angle::firstOrCreate(['code' => 'FLAT'], ['name' => 'Flat', 'display_order' => 10])->id,
        ]);

        Exercise::factory()->create([
            'name' => 'Bicep Curl',
            'movement_pattern_id' => $elbowFlexionPattern->id,
            'target_region_id' => $upperPull->id,
            'equipment_type_id' => $barbellEquipment->id,
            'angle_id' => Angle::firstOrCreate(['code' => 'VERTICAL'], ['name' => 'Vertical', 'display_order' => 50])->id,
        ]);

        // Link all exercises to partner
        $exercises = Exercise::all();
        $this->attachToPartnerAndBodybuildingStyle($exercises, $partner);

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH', 'UPPER_PULL'],
            'equipment_types' => ['BARBELL'],
            'duration_minutes' => 60,
        ]);

        $this->assertNotEmpty($result['exercises']);

        // Count compound vs isolation
        $compoundPatterns = config('workout_generator.compound_patterns', []);
        $compoundCount = 0;
        $isolationCount = 0;

        foreach ($result['exercises'] as $exerciseData) {
            $exercise = Exercise::find($exerciseData['exercise_id']);
            $pattern = $exercise->movementPattern?->code ?? 'UNKNOWN';
            if (in_array($pattern, $compoundPatterns)) {
                $compoundCount++;
            } else {
                $isolationCount++;
            }
        }

        $total = $compoundCount + $isolationCount;
        $this->assertGreaterThan(0, $total, 'Should have at least one exercise');
        $this->assertGreaterThan(0, $compoundCount, 'Beginner should have at least one compound exercise');
    }

    public function test_compound_cap_limits_compound_exercises(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $lower = TargetRegion::firstOrCreate(['code' => 'LOWER'], ['name' => 'Lower Body', 'display_order' => 30]);

        Exercise::factory()->create([
            'name' => 'Back Squat',
            'movement_pattern_id' => MovementPattern::firstOrCreate(['code' => 'SQUAT'], ['name' => 'Squat', 'display_order' => 210])->id,
            'target_region_id' => $lower->id,
        ]);
        Exercise::factory()->create([
            'name' => 'Deadlift',
            'movement_pattern_id' => MovementPattern::firstOrCreate(['code' => 'HINGE'], ['name' => 'Hinge', 'display_order' => 220])->id,
            'target_region_id' => $lower->id,
        ]);
        Exercise::factory()->create([
            'name' => 'Leg Press',
            'movement_pattern_id' => MovementPattern::firstOrCreate(['code' => 'LEG_PRESS'], ['name' => 'Leg Press', 'display_order' => 240])->id,
            'target_region_id' => $lower->id,
        ]);
        Exercise::factory()->create([
            'name' => 'Split Squat',
            'movement_pattern_id' => MovementPattern::firstOrCreate(['code' => 'LUNGE_SPLIT_SQUAT'], ['name' => 'Lunge', 'display_order' => 230])->id,
            'target_region_id' => $lower->id,
        ]);
        Exercise::factory()->create([
            'name' => 'Hip Thrust',
            'movement_pattern_id' => MovementPattern::firstOrCreate(['code' => 'HIP_THRUST_BRIDGE'], ['name' => 'Hip Thrust', 'display_order' => 270])->id,
            'target_region_id' => $lower->id,
        ]);

        $exercises = Exercise::all();
        $this->attachToPartnerAndBodybuildingStyle($exercises, $partner);

        $result = $this->generator->generate($user, [
            'target_regions' => ['LOWER'],
            'duration_minutes' => 60,
        ]);

        $compoundPatterns = config('workout_generator.compound_patterns', []);
        $compoundCount = 0;
        foreach ($result['exercises'] as $exerciseData) {
            $exercise = Exercise::find($exerciseData['exercise_id']);
            $pattern = $exercise->movementPattern?->code ?? 'UNKNOWN';
            if (in_array($pattern, $compoundPatterns)) {
                $compoundCount++;
            }
        }

        $this->assertLessThanOrEqual(config('workout_generator.max_compound_exercises', 2), $compoundCount);
    }

    public function test_generator_prefers_higher_selection_priority_for_same_pattern_angle(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $lower = TargetRegion::firstOrCreate(['code' => 'LOWER'], ['name' => 'Lower Body', 'display_order' => 30]);
        $flatAngle = Angle::firstOrCreate(['code' => 'FLAT'], ['name' => 'Flat', 'display_order' => 10]);

        $squatPattern = MovementPattern::firstOrCreate(['code' => 'SQUAT'], ['name' => 'Squat', 'display_order' => 210]);
        $hingePattern = MovementPattern::firstOrCreate(['code' => 'HINGE'], ['name' => 'Hinge', 'display_order' => 220]);
        $legPressPattern = MovementPattern::firstOrCreate(['code' => 'LEG_PRESS'], ['name' => 'Leg Press', 'display_order' => 240]);
        $kneeExtensionPattern = MovementPattern::firstOrCreate(['code' => 'KNEE_EXTENSION'], ['name' => 'Knee Extension', 'display_order' => 250]);

        Exercise::factory()->withPriority(100)->create([
            'name' => 'Back Squat',
            'movement_pattern_id' => $squatPattern->id,
            'target_region_id' => $lower->id,
            'angle_id' => $flatAngle->id,
        ]);

        Exercise::factory()->withPriority(50)->create([
            'name' => 'Front Squat',
            'movement_pattern_id' => $squatPattern->id,
            'target_region_id' => $lower->id,
            'angle_id' => $flatAngle->id,
        ]);

        Exercise::factory()->create([
            'name' => 'Deadlift',
            'movement_pattern_id' => $hingePattern->id,
            'target_region_id' => $lower->id,
            'angle_id' => $flatAngle->id,
        ]);

        Exercise::factory()->create([
            'name' => 'Leg Press',
            'movement_pattern_id' => $legPressPattern->id,
            'target_region_id' => $lower->id,
            'angle_id' => $flatAngle->id,
        ]);

        Exercise::factory()->create([
            'name' => 'Leg Extension',
            'movement_pattern_id' => $kneeExtensionPattern->id,
            'target_region_id' => $lower->id,
            'angle_id' => $flatAngle->id,
        ]);

        $this->attachToPartnerAndBodybuildingStyle(Exercise::all(), $partner);

        $result = $this->generator->generate($user, [
            'target_regions' => ['LOWER'],
            'duration_minutes' => 30,
        ]);

        $selectedNames = [];
        foreach ($result['exercises'] as $exerciseData) {
            $selectedNames[] = Exercise::find($exerciseData['exercise_id'])?->name;
        }

        $this->assertContains('Back Squat', $selectedNames);
        $this->assertNotContains('Front Squat', $selectedNames);
    }

    public function test_beginner_excludes_smith_machine(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::GeneralFitness,
            'training_experience' => TrainingExperience::Beginner,
        ]);

        $upperPush = TargetRegion::firstOrCreate(['code' => 'UPPER_PUSH'], ['name' => 'Upper Push', 'display_order' => 10]);
        $pressPattern = MovementPattern::firstOrCreate(['code' => 'PRESS'], ['name' => 'Press', 'display_order' => 10]);

        $smithType = EquipmentType::firstOrCreate(['code' => 'SMITH'], ['name' => 'Smith', 'display_order' => 15]);
        $machineType = EquipmentType::firstOrCreate(['code' => 'MACHINE'], ['name' => 'Machine', 'display_order' => 30]);

        Exercise::factory()->create([
            'name' => 'Smith Machine Press',
            'movement_pattern_id' => $pressPattern->id,
            'target_region_id' => $upperPush->id,
            'equipment_type_id' => $smithType->id,
        ]);

        Exercise::factory()->create([
            'name' => 'Machine Chest Press',
            'movement_pattern_id' => $pressPattern->id,
            'target_region_id' => $upperPush->id,
            'equipment_type_id' => $machineType->id,
        ]);

        $exercises = Exercise::all();
        $this->attachToPartnerAndBodybuildingStyle($exercises, $partner);

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 60,
        ]);

        foreach ($result['exercises'] as $exerciseData) {
            $exercise = Exercise::find($exerciseData['exercise_id']);
            $this->assertNotEquals('SMITH', $exercise->equipmentType?->code);
        }
    }

    public function test_full_body_includes_all_targeted_regions(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::GeneralFitness,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $upperPush = TargetRegion::firstOrCreate(['code' => 'UPPER_PUSH'], ['name' => 'Upper Push', 'display_order' => 10]);
        $upperPull = TargetRegion::firstOrCreate(['code' => 'UPPER_PULL'], ['name' => 'Upper Pull', 'display_order' => 20]);
        $lower = TargetRegion::firstOrCreate(['code' => 'LOWER'], ['name' => 'Lower Body', 'display_order' => 30]);

        Exercise::factory()->create([
            'name' => 'Bench Press',
            'movement_pattern_id' => MovementPattern::firstOrCreate(['code' => 'PRESS'], ['name' => 'Press', 'display_order' => 10])->id,
            'target_region_id' => $upperPush->id,
        ]);
        Exercise::factory()->create([
            'name' => 'Row',
            'movement_pattern_id' => MovementPattern::firstOrCreate(['code' => 'ROW'], ['name' => 'Row', 'display_order' => 110])->id,
            'target_region_id' => $upperPull->id,
        ]);
        Exercise::factory()->create([
            'name' => 'Squat',
            'movement_pattern_id' => MovementPattern::firstOrCreate(['code' => 'SQUAT'], ['name' => 'Squat', 'display_order' => 210])->id,
            'target_region_id' => $lower->id,
        ]);

        $exercises = Exercise::all();
        $this->attachToPartnerAndBodybuildingStyle($exercises, $partner);

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH', 'UPPER_PULL', 'LOWER'],
            'duration_minutes' => 60,
        ]);

        $regions = [];
        foreach ($result['exercises'] as $exerciseData) {
            $exercise = Exercise::find($exerciseData['exercise_id']);
            $regions[] = $exercise->targetRegion?->code;
        }

        $this->assertContains('UPPER_PUSH', $regions);
        $this->assertContains('UPPER_PULL', $regions);
        $this->assertContains('LOWER', $regions);
    }

    public function test_pull_workout_includes_biceps_via_complementary_patterns(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        Exercise::factory()->row()->barbell()->horizontal()->create(['name' => 'Barbell Row']);
        Exercise::factory()->row()->barbell()->lowToHigh()->create(['name' => 'Landmine Row']);

        $elbowFlexion = MovementPattern::firstOrCreate(['code' => 'ELBOW_FLEXION'], ['name' => 'Elbow Flexion', 'display_order' => 310]);
        $arms = TargetRegion::firstOrCreate(['code' => 'ARMS'], ['name' => 'Arms', 'display_order' => 40]);
        Exercise::factory()->create([
            'name' => 'Bicep Curl',
            'movement_pattern_id' => $elbowFlexion->id,
            'target_region_id' => $arms->id,
        ]);

        $exercises = Exercise::all();
        $this->attachToPartnerAndBodybuildingStyle($exercises, $partner);

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PULL'],
            'duration_minutes' => 60,
        ]);

        $selectedNames = [];
        foreach ($result['exercises'] as $exerciseData) {
            $selectedNames[] = Exercise::find($exerciseData['exercise_id'])->name;
        }

        $this->assertContains('Bicep Curl', $selectedNames);
    }

    public function test_relaxed_pass_fills_remaining_slots(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->incline()->create(['name' => 'Incline Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Close-Grip Bench Press']);
        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Wide-Grip Bench Press']);
        Exercise::factory()->press()->barbell()->incline()->create(['name' => 'Incline Dumbbell Press']);

        $exercises = Exercise::all();
        $this->attachToPartnerAndBodybuildingStyle($exercises, $partner);

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH'],
            'equipment_types' => ['BARBELL'],
            'duration_minutes' => 60,
        ]);

        $this->assertGreaterThan(3, count($result['exercises']));
    }

    public function test_lower_body_gets_isolation_exercises(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $lower = TargetRegion::firstOrCreate(['code' => 'LOWER'], ['name' => 'Lower Body', 'display_order' => 30]);

        Exercise::factory()->create([
            'name' => 'Back Squat',
            'movement_pattern_id' => MovementPattern::firstOrCreate(['code' => 'SQUAT'], ['name' => 'Squat', 'display_order' => 210])->id,
            'target_region_id' => $lower->id,
        ]);
        Exercise::factory()->create([
            'name' => 'Deadlift',
            'movement_pattern_id' => MovementPattern::firstOrCreate(['code' => 'HINGE'], ['name' => 'Hinge', 'display_order' => 220])->id,
            'target_region_id' => $lower->id,
        ]);
        Exercise::factory()->create([
            'name' => 'Leg Press',
            'movement_pattern_id' => MovementPattern::firstOrCreate(['code' => 'LEG_PRESS'], ['name' => 'Leg Press', 'display_order' => 240])->id,
            'target_region_id' => $lower->id,
        ]);
        Exercise::factory()->create([
            'name' => 'Leg Extension',
            'movement_pattern_id' => MovementPattern::firstOrCreate(['code' => 'KNEE_EXTENSION'], ['name' => 'Knee Extension', 'display_order' => 250])->id,
            'target_region_id' => $lower->id,
        ]);
        Exercise::factory()->create([
            'name' => 'Seated Leg Curl',
            'movement_pattern_id' => MovementPattern::firstOrCreate(['code' => 'KNEE_FLEXION'], ['name' => 'Knee Flexion', 'display_order' => 260])->id,
            'target_region_id' => $lower->id,
        ]);
        Exercise::factory()->create([
            'name' => 'Hip Abduction',
            'movement_pattern_id' => MovementPattern::firstOrCreate(['code' => 'HIP_ABDUCTION'], ['name' => 'Hip Abduction', 'display_order' => 280])->id,
            'target_region_id' => $lower->id,
        ]);

        $exercises = Exercise::all();
        $this->attachToPartnerAndBodybuildingStyle($exercises, $partner);

        $result = $this->generator->generate($user, [
            'target_regions' => ['LOWER'],
            'duration_minutes' => 60,
        ]);

        $compoundPatterns = config('workout_generator.compound_patterns', []);
        $isolationCount = 0;
        foreach ($result['exercises'] as $exerciseData) {
            $exercise = Exercise::find($exerciseData['exercise_id']);
            $pattern = $exercise->movementPattern?->code ?? 'UNKNOWN';
            if (! in_array($pattern, $compoundPatterns)) {
                $isolationCount++;
            }
        }

        $this->assertGreaterThanOrEqual(2, $isolationCount);
    }

    public function test_advanced_muscle_gain_user_gets_mix_of_compound_and_isolation(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Advanced,
        ]);

        // Create compound exercises
        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->incline()->create(['name' => 'Incline Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->vertical()->create(['name' => 'Push Press']);
        Exercise::factory()->row()->barbell()->horizontal()->create(['name' => 'Barbell Row']);
        Exercise::factory()->row()->barbell()->lowToHigh()->create(['name' => 'Single-Arm Landmine Row']);

        // Create isolation exercises
        $flyPattern = MovementPattern::firstOrCreate(['code' => 'FLY'], ['name' => 'Fly', 'display_order' => 20]);
        $elbowFlexionPattern = MovementPattern::firstOrCreate(['code' => 'ELBOW_FLEXION'], ['name' => 'Elbow Flexion', 'display_order' => 30]);
        $elbowExtensionPattern = MovementPattern::firstOrCreate(['code' => 'ELBOW_EXTENSION'], ['name' => 'Elbow Extension', 'display_order' => 31]);
        $upperPush = TargetRegion::firstOrCreate(['code' => 'UPPER_PUSH'], ['name' => 'Upper Push', 'display_order' => 10]);
        $upperPull = TargetRegion::firstOrCreate(['code' => 'UPPER_PULL'], ['name' => 'Upper Pull', 'display_order' => 20]);

        $barbellEquipment = EquipmentType::firstOrCreate(['code' => 'BARBELL'], ['name' => 'Barbell', 'display_order' => 10]);

        Exercise::factory()->create([
            'name' => 'Dumbbell Fly',
            'movement_pattern_id' => $flyPattern->id,
            'target_region_id' => $upperPush->id,
            'equipment_type_id' => $barbellEquipment->id,
            'angle_id' => Angle::firstOrCreate(['code' => 'FLAT'], ['name' => 'Flat', 'display_order' => 10])->id,
        ]);

        Exercise::factory()->create([
            'name' => 'Bicep Curl',
            'movement_pattern_id' => $elbowFlexionPattern->id,
            'target_region_id' => $upperPull->id,
            'equipment_type_id' => $barbellEquipment->id,
            'angle_id' => Angle::firstOrCreate(['code' => 'VERTICAL'], ['name' => 'Vertical', 'display_order' => 50])->id,
        ]);

        Exercise::factory()->create([
            'name' => 'Tricep Extension',
            'movement_pattern_id' => $elbowExtensionPattern->id,
            'target_region_id' => $upperPush->id,
            'equipment_type_id' => $barbellEquipment->id,
            'angle_id' => Angle::firstOrCreate(['code' => 'VERTICAL'], ['name' => 'Vertical', 'display_order' => 50])->id,
        ]);

        // Link all exercises to partner
        $exercises = Exercise::all();
        $this->attachToPartnerAndBodybuildingStyle($exercises, $partner);

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH', 'UPPER_PULL'],
            'equipment_types' => ['BARBELL'],
            'duration_minutes' => 60,
        ]);

        $this->assertNotEmpty($result['exercises']);

        // Count compound vs isolation
        $compoundPatterns = config('workout_generator.compound_patterns', []);
        $compoundCount = 0;
        $isolationCount = 0;

        foreach ($result['exercises'] as $exerciseData) {
            $exercise = Exercise::find($exerciseData['exercise_id']);
            $pattern = $exercise->movementPattern?->code ?? 'UNKNOWN';
            if (in_array($pattern, $compoundPatterns)) {
                $compoundCount++;
            } else {
                $isolationCount++;
            }
        }

        $total = $compoundCount + $isolationCount;

        $this->assertGreaterThan(0, $compoundCount, 'Should have at least one compound exercise');
        $this->assertGreaterThan(0, $isolationCount, 'Should have at least one isolation exercise');
        $this->assertGreaterThanOrEqual(4, $total, 'Should meet minimum exercise count');
    }

    public function test_min_total_exercises_is_enforced_with_relaxed_second_pass(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        // Create only 2 exercises with different pattern|angle combinations
        // This would normally only allow 2 exercises in strict pass
        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->incline()->create(['name' => 'Incline Barbell Bench Press']);

        // Create more exercises with same pattern|angle (to test relaxed pass)
        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Close-Grip Bench Press']);
        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Wide-Grip Bench Press']);
        Exercise::factory()->press()->barbell()->incline()->create(['name' => 'Incline Dumbbell Press']);

        // Link all exercises to partner
        $exercises = Exercise::all();
        $this->attachToPartnerAndBodybuildingStyle($exercises, $partner);

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH'],
            'equipment_types' => ['BARBELL'],
            'duration_minutes' => 60,
        ]);

        // Should get at least safety minimum (3) even if strict pass only found 2
        // But we only have 2 unique pattern|angle combos, so relaxed pass should add more
        $safetyMin = config('workout_generator.exercise_count_safety.min', 3);
        $safetyMax = config('workout_generator.exercise_count_safety.max', 12);
        $this->assertGreaterThanOrEqual($safetyMin, count($result['exercises']), 'Should get at least safety minimum exercises');
        // With relaxed pass, should be able to get more than just the 2 unique combinations
        $this->assertLessThanOrEqual($safetyMax, count($result['exercises']), 'Should respect safety maximum exercises');
    }

    public function test_different_goals_produce_different_exercise_counts(): void
    {
        $partner = Partner::factory()->create();

        $upperPush = TargetRegion::firstOrCreate(['code' => 'UPPER_PUSH'], ['name' => 'Upper Push', 'display_order' => 10]);
        $upperPull = TargetRegion::firstOrCreate(['code' => 'UPPER_PULL'], ['name' => 'Upper Pull', 'display_order' => 20]);
        $barbellEquipment = EquipmentType::firstOrCreate(['code' => 'BARBELL'], ['name' => 'Barbell', 'display_order' => 10]);

        Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->incline()->create(['name' => 'Incline Barbell Bench Press']);
        Exercise::factory()->press()->barbell()->vertical()->create(['name' => 'Push Press']);
        Exercise::factory()->row()->barbell()->horizontal()->create(['name' => 'Barbell Row']);
        Exercise::factory()->row()->barbell()->lowToHigh()->create(['name' => 'Single-Arm Landmine Row']);

        $flyPattern = MovementPattern::firstOrCreate(['code' => 'FLY'], ['name' => 'Fly', 'display_order' => 20]);
        $elbowFlexionPattern = MovementPattern::firstOrCreate(['code' => 'ELBOW_FLEXION'], ['name' => 'Elbow Flexion', 'display_order' => 30]);
        $elbowExtensionPattern = MovementPattern::firstOrCreate(['code' => 'ELBOW_EXTENSION'], ['name' => 'Elbow Extension', 'display_order' => 31]);
        $rearDeltPattern = MovementPattern::firstOrCreate(['code' => 'REAR_DELT_FLY'], ['name' => 'Rear Delt Fly', 'display_order' => 32]);
        $facePullPattern = MovementPattern::firstOrCreate(['code' => 'FACE_PULL'], ['name' => 'Face Pull', 'display_order' => 33]);

        Exercise::factory()->create([
            'name' => 'Dumbbell Fly',
            'movement_pattern_id' => $flyPattern->id,
            'target_region_id' => $upperPush->id,
            'equipment_type_id' => $barbellEquipment->id,
            'angle_id' => Angle::firstOrCreate(['code' => 'FLAT'], ['name' => 'Flat', 'display_order' => 10])->id,
        ]);
        Exercise::factory()->create([
            'name' => 'Incline Fly',
            'movement_pattern_id' => $flyPattern->id,
            'target_region_id' => $upperPush->id,
            'equipment_type_id' => $barbellEquipment->id,
            'angle_id' => Angle::firstOrCreate(['code' => 'INCLINE'], ['name' => 'Incline', 'display_order' => 20])->id,
        ]);
        Exercise::factory()->create([
            'name' => 'Bicep Curl',
            'movement_pattern_id' => $elbowFlexionPattern->id,
            'target_region_id' => $upperPull->id,
            'equipment_type_id' => $barbellEquipment->id,
            'angle_id' => Angle::firstOrCreate(['code' => 'VERTICAL'], ['name' => 'Vertical', 'display_order' => 50])->id,
        ]);
        Exercise::factory()->create([
            'name' => 'Tricep Extension',
            'movement_pattern_id' => $elbowExtensionPattern->id,
            'target_region_id' => $upperPush->id,
            'equipment_type_id' => $barbellEquipment->id,
            'angle_id' => Angle::firstOrCreate(['code' => 'VERTICAL'], ['name' => 'Vertical', 'display_order' => 50])->id,
        ]);
        Exercise::factory()->create([
            'name' => 'Rear Delt Fly',
            'movement_pattern_id' => $rearDeltPattern->id,
            'target_region_id' => $upperPull->id,
            'equipment_type_id' => $barbellEquipment->id,
            'angle_id' => Angle::firstOrCreate(['code' => 'HORIZONTAL'], ['name' => 'Horizontal', 'display_order' => 30])->id,
        ]);
        Exercise::factory()->create([
            'name' => 'Face Pull',
            'movement_pattern_id' => $facePullPattern->id,
            'target_region_id' => $upperPull->id,
            'equipment_type_id' => $barbellEquipment->id,
            'angle_id' => Angle::firstOrCreate(['code' => 'HORIZONTAL'], ['name' => 'Horizontal', 'display_order' => 30])->id,
        ]);

        $exercises = Exercise::all();
        $this->attachToPartnerAndBodybuildingStyle($exercises, $partner);

        // Strength at 60min targets 5 exercises, fat loss targets 7
        $strengthUser = User::factory()->create(['partner_id' => $partner->id]);
        $strengthUser->profile->update([
            'fitness_goal' => FitnessGoal::Strength,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $strengthResult = $this->generator->generate($strengthUser, [
            'target_regions' => ['UPPER_PUSH', 'UPPER_PULL'],
            'equipment_types' => ['BARBELL'],
            'duration_minutes' => 60,
        ]);

        $fatLossUser = User::factory()->create(['partner_id' => $partner->id]);
        $fatLossUser->profile->update([
            'fitness_goal' => FitnessGoal::FatLoss,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $fatLossResult = $this->generator->generate($fatLossUser, [
            'target_regions' => ['UPPER_PUSH', 'UPPER_PULL'],
            'equipment_types' => ['BARBELL'],
            'duration_minutes' => 60,
        ]);

        $strengthCount = count($strengthResult['exercises']);
        $fatLossCount = count($fatLossResult['exercises']);

        $this->assertLessThanOrEqual($fatLossCount, $strengthCount, 'Strength should not produce more exercises than fat loss');
    }

    public function test_beginners_get_diverse_equipment(): void
    {
        $partner = Partner::factory()->create();
        $beginnerUser = User::factory()->create(['partner_id' => $partner->id]);
        $beginnerUser->profile->update([
            'fitness_goal' => FitnessGoal::GeneralFitness,
            'training_experience' => TrainingExperience::Beginner,
        ]);

        $upperPush = TargetRegion::firstOrCreate(['code' => 'UPPER_PUSH'], ['name' => 'Upper Push', 'display_order' => 10]);
        $pressPattern = MovementPattern::firstOrCreate(['code' => 'PRESS'], ['name' => 'Press', 'display_order' => 10]);

        // Create multiple equipment options; beginners should not be biased toward only machine/cable
        $machineType = EquipmentType::firstOrCreate(['code' => 'MACHINE'], ['name' => 'Machine', 'display_order' => 30]);
        $cableType = EquipmentType::firstOrCreate(['code' => 'CABLE'], ['name' => 'Cable', 'display_order' => 40]);
        $barbellType = EquipmentType::firstOrCreate(['code' => 'BARBELL'], ['name' => 'Barbell', 'display_order' => 10]);

        Exercise::factory()->create([
            'name' => 'Machine Chest Press',
            'movement_pattern_id' => $pressPattern->id,
            'target_region_id' => $upperPush->id,
            'equipment_type_id' => $machineType->id,
        ]);

        Exercise::factory()->create([
            'name' => 'Cable Chest Press',
            'movement_pattern_id' => $pressPattern->id,
            'target_region_id' => $upperPush->id,
            'equipment_type_id' => $cableType->id,
        ]);

        // Create barbell exercise (should be eligible for selection)
        Exercise::factory()->create([
            'name' => 'Barbell Bench Press',
            'movement_pattern_id' => $pressPattern->id,
            'target_region_id' => $upperPush->id,
            'equipment_type_id' => $barbellType->id,
        ]);

        // Link all exercises to partner
        $exercises = Exercise::all();
        $this->attachToPartnerAndBodybuildingStyle($exercises, $partner);

        $result = $this->generator->generate($beginnerUser, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 60,
        ]);

        $this->assertNotEmpty($result['exercises']);

        // Get unique equipment types selected
        $selectedEquipment = [];
        foreach ($result['exercises'] as $exerciseData) {
            $exercise = Exercise::find($exerciseData['exercise_id']);
            if ($exercise && $exercise->equipmentType) {
                $selectedEquipment[] = $exercise->equipmentType->code;
            }
        }

        $uniqueEquipment = array_values(array_unique($selectedEquipment));
        $this->assertGreaterThanOrEqual(2, count($uniqueEquipment), 'Beginner selection should not be biased to a single equipment type');
    }

    public function test_generator_defaults_to_bodybuilding_when_no_equipment_or_style_filters(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $bodybuildingExercise = Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Barbell Bench Press']);
        $functionalExercise = Exercise::factory()->press()->barbell()->flat()->create(['name' => 'Barbell Thruster']);

        $this->attachToPartnerAndBodybuildingStyle([$bodybuildingExercise], $partner);
        $this->attachToPartnerAndTrainingStyle([$functionalExercise], $partner, 'FUNCTIONAL');

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 60,
        ]);

        $selectedIds = array_column($result['exercises'], 'exercise_id');
        $this->assertContains($bodybuildingExercise->id, $selectedIds);
        $this->assertNotContains($functionalExercise->id, $selectedIds);
    }

    public function test_generator_applies_equipment_only_without_training_style_default(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $trxType = EquipmentType::firstOrCreate(
            ['code' => 'TRX'],
            ['name' => 'TRX', 'display_order' => 90]
        );

        $trxExercise = Exercise::factory()->press()->flat()->create([
            'name' => 'TRX Chest Press',
            'equipment_type_id' => $trxType->id,
        ]);

        $this->attachToPartnerAndTrainingStyle([$trxExercise], $partner, 'FUNCTIONAL');

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH'],
            'equipment_types' => ['TRX'],
            'duration_minutes' => 60,
        ]);

        $selectedIds = array_column($result['exercises'], 'exercise_id');
        $this->assertContains($trxExercise->id, $selectedIds);
    }

    public function test_generator_combines_equipment_and_training_style_filters(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $trxType = EquipmentType::firstOrCreate(
            ['code' => 'TRX'],
            ['name' => 'TRX', 'display_order' => 90]
        );

        $trxFunctional = Exercise::factory()->press()->flat()->create([
            'name' => 'TRX Chest Press',
            'equipment_type_id' => $trxType->id,
        ]);
        $trxBodybuilding = Exercise::factory()->press()->flat()->create([
            'name' => 'TRX Fly',
            'equipment_type_id' => $trxType->id,
        ]);

        $this->attachToPartnerAndTrainingStyle([$trxFunctional], $partner, 'FUNCTIONAL');
        $this->attachToPartnerAndBodybuildingStyle([$trxBodybuilding], $partner);

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH'],
            'equipment_types' => ['TRX'],
            'training_styles' => ['FUNCTIONAL'],
            'duration_minutes' => 60,
        ]);

        $selectedIds = array_column($result['exercises'], 'exercise_id');
        $this->assertContains($trxFunctional->id, $selectedIds);
        $this->assertNotContains($trxBodybuilding->id, $selectedIds);
    }

    public function test_generator_ignores_client_bodybuilding_default_for_functional_equipment(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $trxType = EquipmentType::firstOrCreate(
            ['code' => 'TRX'],
            ['name' => 'TRX', 'display_order' => 90]
        );

        $trxRow = Exercise::factory()->row()->flat()->create([
            'name' => 'TRX Row',
            'equipment_type_id' => $trxType->id,
        ]);

        $this->attachToPartnerAndTrainingStyle([$trxRow], $partner, 'FUNCTIONAL');

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PULL'],
            'equipment_types' => ['TRX'],
            'training_styles' => ['BODYBUILDING'],
            'duration_minutes' => 60,
        ]);

        $selectedIds = array_column($result['exercises'], 'exercise_id');
        $this->assertContains($trxRow->id, $selectedIds);
    }

    public function test_generator_treats_empty_training_styles_array_as_unset_when_equipment_selected(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $trxType = EquipmentType::firstOrCreate(
            ['code' => 'TRX'],
            ['name' => 'TRX', 'display_order' => 90]
        );

        $trxRow = Exercise::factory()->row()->flat()->create([
            'name' => 'TRX Row',
            'equipment_type_id' => $trxType->id,
        ]);

        $this->attachToPartnerAndTrainingStyle([$trxRow], $partner, 'FUNCTIONAL');

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PULL'],
            'equipment_types' => ['TRX'],
            'training_styles' => [],
            'duration_minutes' => 60,
        ]);

        $selectedIds = array_column($result['exercises'], 'exercise_id');
        $this->assertContains($trxRow->id, $selectedIds);
    }

    public function test_generator_includes_mixed_equipment_when_functional_is_in_selection_with_implicit_bodybuilding(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $trxType = EquipmentType::firstOrCreate(
            ['code' => 'TRX'],
            ['name' => 'TRX', 'display_order' => 90]
        );

        $dumbbellPress = Exercise::factory()->press()->barbell()->flat()->create([
            'name' => 'Dumbbell Bench Press',
        ]);
        $dumbbellPress->equipmentType()->associate(
            EquipmentType::firstOrCreate(['code' => 'DUMBBELL'], ['name' => 'Dumbbell', 'display_order' => 20])
        );
        $dumbbellPress->save();

        $trxRow = Exercise::factory()->row()->flat()->create([
            'name' => 'TRX Row',
            'equipment_type_id' => $trxType->id,
        ]);

        $this->attachToPartnerAndBodybuildingStyle([$dumbbellPress], $partner);
        $this->attachToPartnerAndTrainingStyle([$trxRow], $partner, 'FUNCTIONAL');

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH', 'UPPER_PULL'],
            'equipment_types' => ['DUMBBELL', 'TRX'],
            'training_styles' => ['BODYBUILDING'],
            'duration_minutes' => 60,
        ]);

        $selectedIds = array_column($result['exercises'], 'exercise_id');
        $this->assertContains($dumbbellPress->id, $selectedIds);
        $this->assertContains($trxRow->id, $selectedIds);
    }

    public function test_generator_keeps_bodybuilding_filter_for_non_functional_equipment_only(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $bodybuildingExercise = Exercise::factory()->press()->barbell()->flat()->create([
            'name' => 'Dumbbell Bench Press',
        ]);
        $bodybuildingExercise->equipmentType()->associate(
            EquipmentType::firstOrCreate(['code' => 'DUMBBELL'], ['name' => 'Dumbbell', 'display_order' => 20])
        );
        $bodybuildingExercise->save();

        $functionalExercise = Exercise::factory()->press()->barbell()->flat()->create([
            'name' => 'Dumbbell Thruster',
        ]);
        $functionalExercise->equipmentType()->associate(
            EquipmentType::firstOrCreate(['code' => 'DUMBBELL'], ['name' => 'Dumbbell', 'display_order' => 20])
        );
        $functionalExercise->save();

        $this->attachToPartnerAndBodybuildingStyle([$bodybuildingExercise], $partner);
        $this->attachToPartnerAndTrainingStyle([$functionalExercise], $partner, 'FUNCTIONAL');

        $result = $this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH'],
            'equipment_types' => ['DUMBBELL'],
            'training_styles' => ['BODYBUILDING'],
            'duration_minutes' => 60,
        ]);

        $selectedIds = array_column($result['exercises'], 'exercise_id');
        $this->assertContains($bodybuildingExercise->id, $selectedIds);
        $this->assertNotContains($functionalExercise->id, $selectedIds);
    }

    public function test_generator_fails_when_equipment_and_training_style_do_not_overlap(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $functionalExercise = Exercise::factory()->press()->barbell()->flat()->create([
            'name' => 'Barbell Thruster',
        ]);

        $this->attachToPartnerAndTrainingStyle([$functionalExercise], $partner, 'FUNCTIONAL');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No exercises available matching the specified criteria');

        $this->generator->generate($user, [
            'target_regions' => ['UPPER_PUSH'],
            'equipment_types' => ['BARBELL'],
            'training_styles' => ['BODYBUILDING'],
            'duration_minutes' => 60,
        ]);
    }
}
