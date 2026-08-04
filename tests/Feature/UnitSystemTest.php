<?php

namespace Tests\Feature;

use App\Enums\TrainingExperience;
use App\Enums\UnitSystem;
use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\UnitConversionService;
use App\Services\WorkoutGenerator\ProgressionCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_imperial_user_profile_shows_converted_weight_and_height(): void
    {
        $user = User::factory()->create();
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
        $user = User::factory()->create();
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
        $user = User::factory()->create();
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
        $user = User::factory()->create();
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
        $user = User::factory()->create();
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
        $user = User::factory()->create();
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
        $metricUser = User::factory()->create();
        $imperialUser = User::factory()->create();

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

        $metricResponse = $this
            ->actingAs($metricUser, 'sanctum')
            ->getJson('/api/user/fitness-metrics');

        $imperialResponse = $this
            ->actingAs($imperialUser, 'sanctum')
            ->getJson('/api/user/fitness-metrics');

        $metricResponse->assertOk();
        $imperialResponse->assertOk();

        $this->assertEquals(
            $metricResponse->json('data'),
            $imperialResponse->json('data'),
            'FitnessMetricsService output must be identical regardless of unit_system - unit preference must never leak into its internal kg-based math.'
        );
    }

    /**
     * Same invariant check for ProgressionCalculatorService::calculateTargets() -
     * called directly (bypassing HTTP entirely), it must return an identical
     * kg-based target_weight for two users with identical training history and
     * body weight, differing only in unit_system.
     */
    public function test_progression_calculator_target_weight_is_identical_regardless_of_unit_preference(): void
    {
        $metricUser = User::factory()->create();
        $imperialUser = User::factory()->create();

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
}
