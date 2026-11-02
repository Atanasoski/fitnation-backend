<?php

namespace Tests\Feature;

use App\Enums\TrainingExperience;
use App\Enums\UnitSystem;
use App\Enums\WorkoutSessionStatus;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\Plan;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use App\Services\FitnessMetricsService;
use App\Services\UnitConversionService;
use App\Services\WorkoutGenerator\ProgressionCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UnitSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_imperial_user_profile_shows_converted_weight_and_height(): void
    {
        $user = User::factory()->entitled()->create();
        $user->profile->update([
            'weight' => 80.0,
            'height' => 180,
            'unit_system' => UnitSystem::Imperial,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/profile');

        $response->assertOk();

        $this->assertSame('imperial', $response->json('user.profile.unit_system'));
        // 80kg -> 176.36980... lbs, rounded to nearest 0.5 lb (body weight policy) = 176.5
        $this->assertEquals(176.5, $response->json('user.profile.weight'));
        // 180cm -> 70.8661... in, rounded to nearest whole inch = 71
        $this->assertEquals(71, $response->json('user.profile.height'));
    }

    public function test_metric_user_profile_output_is_unchanged(): void
    {
        $user = User::factory()->entitled()->create();
        $user->profile->update([
            'weight' => 82.5,
            'height' => 175,
            'unit_system' => UnitSystem::Metric,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/profile');

        $response->assertOk();

        $this->assertSame('metric', $response->json('user.profile.unit_system'));
        $this->assertEquals(82.5, $response->json('user.profile.weight'));
        $this->assertEquals(175, $response->json('user.profile.height'));
    }

    public function test_imperial_user_set_log_listing_shows_lbs_converted_stepped_weight(): void
    {
        $user = User::factory()->entitled()->create();
        $user->profile->update(['unit_system' => UnitSystem::Imperial]);

        $exercise = Exercise::factory()->create();

        $session = WorkoutSession::factory()->create(['user_id' => $user->id]);

        $session->workoutSessionExercises()->create([
            'exercise_id' => $exercise->id,
            'order' => 1,
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 0,
            'rest_seconds' => 90,
        ]);

        SetLog::create([
            'workout_session_id' => $session->id,
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'weight' => 100.0, // 100kg
            'reps' => 10,
            'rest_seconds' => 60,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson("/api/workout-sessions/{$session->id}");

        $response->assertOk();

        // 100kg -> 220.462... lbs, rounded to nearest 5 lb (training weight policy) = 220
        $this->assertEquals(220, $response->json('data.exercises.0.logged_sets.0.weight'));
    }

    public function test_patch_profile_onboarding_with_imperial_persists_kg_and_cm(): void
    {
        $user = User::factory()->entitled()->create();
        // Simulate "no prior profile row" (onboarding scenario).
        $user->profile()->delete();
        $user->refresh();
        $this->assertNull($user->profile);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', [
                'unit_system' => 'imperial',
                'weight' => 200, // lbs
                'height' => 70,  // inches
            ]);

        $response->assertOk();

        $profile = $user->fresh()->profile;

        $this->assertNotNull($profile);
        $this->assertSame(UnitSystem::Imperial, $profile->unit_system);
        // 200 lbs -> 90.72 kg (decimal:2 column)
        $this->assertEquals(90.72, (float) $profile->weight);
        // 70 in -> 178 cm
        $this->assertEquals(178, $profile->height);
    }

    public function test_patch_profile_uses_stored_preference_when_payload_omits_unit_system(): void
    {
        $user = User::factory()->entitled()->create();
        $user->profile->update(['unit_system' => UnitSystem::Imperial]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', [
                'weight' => 205, // lbs, no unit_system key in this payload
            ]);

        $response->assertOk();

        $profile = $user->fresh()->profile;

        $conversionService = new UnitConversionService;
        $expectedKg = round($conversionService->toKg(205.0, UnitSystem::Imperial), 2);

        $this->assertEquals($expectedKg, (float) $profile->weight);
    }

    public function test_logging_set_with_lbs_weight_persists_kg_for_imperial_user(): void
    {
        $user = User::factory()->entitled()->create();
        $user->profile->update(['unit_system' => UnitSystem::Imperial]);

        $exercise = Exercise::factory()->create();
        $session = WorkoutSession::factory()->create(['user_id' => $user->id]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson("/api/workout-sessions/{$session->id}/sets", [
                'exercise_id' => $exercise->id,
                'set_number' => 1,
                'weight' => 225, // lbs
                'reps' => 5,
            ]);

        $response->assertCreated();

        $setLog = SetLog::where('workout_session_id', $session->id)->firstOrFail();

        // 225 lbs -> 102.058... kg, stored with decimal:1 cast -> 102.1
        $this->assertEquals(102.1, (float) $setLog->weight);
    }

    /**
     * The hard invariant: unit preference must never leak into FitnessMetricsService's
     * or ProgressionCalculatorService's raw kg-based math. Two otherwise-identical
     * users (same completed sessions/set logs, same profile weight in kg) - one
     * metric, one imperial - must produce byte-for-byte identical raw service output.
     */
    public function test_fitness_metrics_service_output_is_identical_regardless_of_unit_preference(): void
    {
        $metricUser = User::factory()->entitled()->create();
        $imperialUser = User::factory()->entitled()->create();

        foreach ([$metricUser, $imperialUser] as $user) {
            $user->profile->update([
                'weight' => 80.0,
                'age' => 30,
                'training_experience' => TrainingExperience::Intermediate,
            ]);
        }

        $metricUser->profile->update(['unit_system' => UnitSystem::Metric]);
        $imperialUser->profile->update(['unit_system' => UnitSystem::Imperial]);

        $chest = MuscleGroup::firstOrCreate(['name' => 'Chest'], ['body_region' => 'upper']);
        $exercise = Exercise::firstOrCreate(
            ['name' => 'Bench Press'],
            ['description' => 'Bench press exercise']
        );
        $exercise->muscleGroups()->syncWithoutDetaching([$chest->id => ['is_primary' => true]]);

        foreach ([$metricUser, $imperialUser] as $user) {
            $session = WorkoutSession::factory()->create([
                'user_id' => $user->id,
                'performed_at' => now()->subDays(5),
                'completed_at' => now()->subDays(5)->addHour(),
                'status' => WorkoutSessionStatus::Completed,
            ]);

            SetLog::create([
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 1,
                'weight' => 100.0,
                'reps' => 8,
                'rest_seconds' => 60,
            ]);

            SetLog::create([
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'set_number' => 2,
                'weight' => 100.0,
                'reps' => 6,
                'rest_seconds' => 60,
            ]);
        }

        // The service itself, not the endpoint: the endpoint converts weekly
        // volumes for display, so its two payloads rightly differ.
        $metrics = app(FitnessMetricsService::class);

        $this->assertEquals(
            $metrics->getMetrics($metricUser->fresh()),
            $metrics->getMetrics($imperialUser->fresh()),
            'FitnessMetricsService output must be identical regardless of unit_system - unit preference must never leak into its internal kg-based math.'
        );
    }

    /**
     * The endpoint converts at the HTTP boundary (ADR-0001): an imperial user's
     * weekly volumes are display totals in lbs, rounded to the whole pound,
     * and the difference is taken between the converted totals. Percentages
     * and counts are unitless and do not move.
     */
    public function test_imperial_user_fitness_metrics_report_weekly_volume_in_lbs(): void
    {
        // "Current week" is the last complete week: Mon 31 Aug - Sun 6 Sep.
        $this->travelTo(Carbon::parse('2026-09-09 12:00:00'));

        $metricUser = User::factory()->entitled()->create();
        $imperialUser = User::factory()->entitled()->create();
        $imperialUser->profile->update(['unit_system' => UnitSystem::Imperial]);
        $exercise = Exercise::factory()->create();

        foreach ([$metricUser, $imperialUser] as $user) {
            $session = WorkoutSession::factory()->create([
                'user_id' => $user->id,
                'performed_at' => '2026-09-01 10:00:00',
                'completed_at' => '2026-09-01 11:00:00',
                'status' => WorkoutSessionStatus::Completed,
            ]);

            foreach ([[1, 8], [2, 6]] as [$setNumber, $reps]) {
                SetLog::create([
                    'workout_session_id' => $session->id,
                    'exercise_id' => $exercise->id,
                    'set_number' => $setNumber,
                    'weight' => 100.0,
                    'reps' => $reps,
                    'rest_seconds' => 60,
                ]);
            }
        }

        $metric = $this->actingAs($metricUser, 'sanctum')->getJson('/api/user/fitness-metrics')
            ->assertOk()->json('data.weekly_progress');
        $imperial = $this->actingAs($imperialUser, 'sanctum')->getJson('/api/user/fitness-metrics')
            ->assertOk()->json('data.weekly_progress');

        // 1,400 kg of volume -> 3,086.47 lbs, rounded to the whole pound.
        $this->assertSame(1400, $metric['current_week_volume']);
        $this->assertSame(3086, $imperial['current_week_volume']);
        $this->assertSame(0, $imperial['previous_week_volume']);
        $this->assertSame(3086, $imperial['volume_difference']);
        $this->assertSame(3086, $imperial['daily_breakdown'][1]['volume']);
        $this->assertSame($metric['volume_difference_percent'], $imperial['volume_difference_percent']);
        $this->assertSame($metric['percentage'], $imperial['percentage']);
    }

    /**
     * An exercise's history goes out in the user's Unit System: a session's
     * weight is its heaviest set, stepped like a logged set, and its volume is
     * a display total. A metric user reads the stored kilograms.
     */
    public function test_imperial_user_exercise_history_is_in_lbs(): void
    {
        $metricUser = User::factory()->entitled()->create();
        $imperialUser = User::factory()->entitled()->create();
        $imperialUser->profile->update(['unit_system' => UnitSystem::Imperial]);
        $exercise = Exercise::factory()->create();

        foreach ([$metricUser, $imperialUser] as $user) {
            $session = WorkoutSession::factory()->create([
                'user_id' => $user->id,
                'performed_at' => now()->subDay(),
                'completed_at' => now()->subDay()->addHour(),
                'status' => WorkoutSessionStatus::Completed,
            ]);

            foreach ([[1, 10], [2, 8]] as [$setNumber, $reps]) {
                SetLog::create([
                    'workout_session_id' => $session->id,
                    'exercise_id' => $exercise->id,
                    'set_number' => $setNumber,
                    'weight' => 100.0,
                    'reps' => $reps,
                    'rest_seconds' => 60,
                ]);
            }
        }

        $metric = $this->actingAs($metricUser, 'sanctum')->getJson("/api/exercises/{$exercise->id}/history")
            ->assertOk()->json('data');
        $imperial = $this->actingAs($imperialUser, 'sanctum')->getJson("/api/exercises/{$exercise->id}/history")
            ->assertOk()->json('data');

        $this->assertSame(100, $metric['performance_data'][0]['weight']);
        $this->assertSame(1800, $metric['performance_data'][0]['volume']);

        // 100 kg -> 220.46 lbs, at the 5 lb training-weight step = 220;
        // 1,800 kg of volume -> 3,968.32 lbs, rounded to the whole pound.
        $this->assertSame(220, $imperial['performance_data'][0]['weight']);
        $this->assertSame(3968, $imperial['performance_data'][0]['volume']);
        $this->assertSame(220, $imperial['stats']['current_weight']);
        $this->assertSame(220, $imperial['stats']['best_weight']);
        $this->assertSame(10, $imperial['stats']['best_set_reps']);
    }

    /**
     * Same invariant check for ProgressionCalculatorService::calculateTargets() -
     * called directly (bypassing HTTP entirely), it must return an identical
     * kg-based target_weight for two users with identical training history and
     * body weight, differing only in unit_system.
     */
    public function test_progression_calculator_target_weight_is_identical_regardless_of_unit_preference(): void
    {
        $metricUser = User::factory()->entitled()->create();
        $imperialUser = User::factory()->entitled()->create();

        foreach ([$metricUser, $imperialUser] as $user) {
            $user->profile->update(['weight' => 80.0]);
        }

        $metricUser->profile->update(['unit_system' => UnitSystem::Metric]);
        $imperialUser->profile->update(['unit_system' => UnitSystem::Imperial]);

        $exercise = Exercise::factory()->barbell()->press()->flat()->create();

        // Identical training history for both users: 3 sets at max target reps
        // (12) with the same weight, which should trigger progressive overload.
        foreach ([$metricUser, $imperialUser] as $user) {
            $session = WorkoutSession::factory()->create([
                'user_id' => $user->id,
                'performed_at' => now()->subDay(),
                'completed_at' => now()->subDay()->addHour(),
                'status' => WorkoutSessionStatus::Completed,
            ]);

            foreach ([1, 2, 3] as $setNumber) {
                SetLog::create([
                    'workout_session_id' => $session->id,
                    'exercise_id' => $exercise->id,
                    'set_number' => $setNumber,
                    'weight' => 60.0,
                    'reps' => 12,
                    'rest_seconds' => 90,
                ]);
            }
        }

        $service = new ProgressionCalculatorService;

        $metricTargets = $service->calculateTargets($exercise, $metricUser->fresh(), TrainingExperience::Intermediate);
        $imperialTargets = $service->calculateTargets($exercise, $imperialUser->fresh(), TrainingExperience::Intermediate);

        $this->assertEquals(
            $metricTargets,
            $imperialTargets,
            'ProgressionCalculatorService::calculateTargets() must return identical raw (kg) output regardless of unit_system.'
        );

        // Sanity check: progressive overload actually triggered (weight > last performance),
        // proving this isn't a trivially-equal all-zero comparison.
        $this->assertGreaterThan(60.0, $metricTargets['target_weight']);
    }

    public function test_updating_template_exercise_with_lbs_target_weight_persists_kg_for_imperial_user(): void
    {
        $user = User::factory()->entitled()->create();
        $user->profile->update(['unit_system' => UnitSystem::Imperial]);

        $exercise = Exercise::factory()->create();
        $plan = Plan::factory()->create(['user_id' => $user->id]);
        $template = WorkoutTemplate::factory()->create(['plan_id' => $plan->id]);

        $pivot = WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise->id,
            'order' => 0,
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 0,
            'rest_seconds' => 90,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson("/api/workout-templates/{$template->id}/exercises/{$pivot->id}", [
                'target_weight' => 225, // lbs
            ]);

        $response->assertOk();

        // 225 lbs -> 102.058... kg stored canonically
        $this->assertEqualsWithDelta(102.06, (float) $pivot->fresh()->target_weight, 0.01);
    }

    public function test_updating_session_exercise_with_lbs_target_weight_persists_kg_for_imperial_user(): void
    {
        $user = User::factory()->entitled()->create();
        $user->profile->update(['unit_system' => UnitSystem::Imperial]);

        $exercise = Exercise::factory()->create();
        $session = WorkoutSession::factory()->create(['user_id' => $user->id]);

        $sessionExercise = $session->workoutSessionExercises()->create([
            'exercise_id' => $exercise->id,
            'order' => 1,
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 0,
            'rest_seconds' => 90,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson("/api/workout-sessions/{$session->id}/exercises/{$sessionExercise->id}", [
                'target_weight' => 135, // lbs
            ]);

        $response->assertOk();

        // 135 lbs -> 61.234... kg stored canonically
        $this->assertEqualsWithDelta(61.23, (float) $sessionExercise->fresh()->target_weight, 0.01);
    }

    public function test_adding_session_exercise_with_lbs_target_weight_persists_kg_for_imperial_user(): void
    {
        $user = User::factory()->entitled()->create();
        $user->profile->update(['unit_system' => UnitSystem::Imperial]);

        $exercise = Exercise::factory()->create();
        $session = WorkoutSession::factory()->create(['user_id' => $user->id]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson("/api/workout-sessions/{$session->id}/exercises", [
                'exercise_id' => $exercise->id,
                'target_weight' => 185, // lbs
            ]);

        $response->assertSuccessful();

        // 185 lbs -> 83.914... kg stored canonically
        $this->assertEqualsWithDelta(
            83.91,
            (float) $session->workoutSessionExercises()->latest('id')->first()->target_weight,
            0.01
        );
    }

    public function test_imperial_weight_exceeding_the_kg_bound_after_conversion_is_rejected(): void
    {
        $user = User::factory()->entitled()->create();
        $user->profile->update(['unit_system' => UnitSystem::Imperial]);

        // 1200 lbs -> 544.31 kg, above the existing max:500 kg bound.
        $response = $this
            ->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', [
                'weight' => 1200,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('weight');
    }

    public function test_profile_update_rejects_a_null_unit_system(): void
    {
        $user = User::factory()->entitled()->create();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', [
                'unit_system' => null,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('unit_system');
    }

    /**
     * Reported 2026-10-12: after a tough 20 lb dumbbell session the app offered
     * "Use 20 lbs / Keep 20". The 2 kg step down (8 kg = 17.6 lb) snapped back
     * to 20 lb; the offer must read one 5 lb step lower.
     */
    public function test_imperial_lowered_weight_reads_one_step_below_when_the_kg_step_snaps_onto_the_target(): void
    {
        // 20 lb is stored as 9.1 kg.
        $row = $this->rowAfterAToughSession(UnitSystem::Imperial, 'DUMBBELL', 9.1);

        $this->assertSame('below_min', $row['progression_status']);
        $this->assertSame(20, $row['target_weight']);
        $this->assertSame(15, $row['target_weight_lowered']);
    }

    public function test_imperial_lowered_weight_keeps_the_equipment_step_when_it_already_reads_lower(): void
    {
        // 100 lb is stored as 45.4 kg; 2.5 kg lower is 42.5 kg, which reads 95 lb.
        $row = $this->rowAfterAToughSession(UnitSystem::Imperial, 'BARBELL', 45.4);

        $this->assertSame(100, $row['target_weight']);
        $this->assertSame(95, $row['target_weight_lowered']);
    }

    public function test_metric_lowered_weight_is_the_equipment_step_unchanged(): void
    {
        $row = $this->rowAfterAToughSession(UnitSystem::Metric, 'DUMBBELL', 9.1);

        $this->assertSame(9.1, $row['target_weight']);
        $this->assertSame(8, $row['target_weight_lowered']);
    }

    /**
     * A live session's row for an exercise whose last completed session was
     * three sets of 5 at the given weight, short of the 8-rep minimum.
     *
     * @return array<string, mixed>
     */
    private function rowAfterAToughSession(UnitSystem $unitSystem, string $equipmentCode, float $lastWeightKg): array
    {
        $user = User::factory()->entitled()->create();
        $user->profile->update(['unit_system' => $unitSystem]);

        $equipmentType = EquipmentType::firstOrCreate(['code' => $equipmentCode], ['name' => $equipmentCode, 'display_order' => 1]);
        $exercise = Exercise::factory()->create(['equipment_type_id' => $equipmentType->id]);

        $history = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'performed_at' => now()->subWeek(),
            'completed_at' => now()->subWeek()->addHour(),
            'status' => WorkoutSessionStatus::Completed,
        ]);

        foreach ([1, 2, 3] as $setNumber) {
            SetLog::create([
                'workout_session_id' => $history->id,
                'exercise_id' => $exercise->id,
                'set_number' => $setNumber,
                'weight' => $lastWeightKg,
                'reps' => 5,
                'rest_seconds' => 90,
            ]);
        }

        $session = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'performed_at' => now(),
            'completed_at' => null,
            'status' => WorkoutSessionStatus::Active,
        ]);

        $session->workoutSessionExercises()->create([
            'exercise_id' => $exercise->id,
            'order' => 0,
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 0,
            'rest_seconds' => 90,
        ]);

        return $this
            ->actingAs($user, 'sanctum')
            ->getJson("/api/workout-sessions/{$session->id}")
            ->assertOk()
            ->json('data.exercises.0.session_exercise');
    }
}
