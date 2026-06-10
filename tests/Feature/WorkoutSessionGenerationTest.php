<?php

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\TrainingExperience;
use App\Enums\WorkoutSessionStatus;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\TargetRegion;
use App\Models\TrainingStyle;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutSessionGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_generate_draft_workout(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile()->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        // Create properly classified exercises
        $exercises = Exercise::factory()->count(5)->press()->barbell()->flat()->create();
        foreach ($exercises as $exercise) {
            $exercise->partners()->attach($partner->id);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/workout-sessions/generate', []);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'is_auto_generated',
                    'status',
                    'replaced_session_id',
                    'rationale',
                    'exercises',
                ],
                'message',
            ]);

        $this->assertNotEmpty($response->json('data.exercises'));
        $this->assertEquals('draft', $response->json('data.status'));
        $this->assertTrue($response->json('data.is_auto_generated'));

        // Verify session was created in database
        $session = WorkoutSession::find($response->json('data.id'));
        $this->assertNotNull($session);
        $this->assertEquals(WorkoutSessionStatus::Draft, $session->status);
        $this->assertNull($session->performed_at); // Draft sessions don't have performed_at
    }

    public function test_user_can_confirm_draft_workout(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile()->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        // Create properly classified exercises
        $exercises = Exercise::factory()->count(3)->press()->barbell()->flat()->create();
        foreach ($exercises as $exercise) {
            $exercise->partners()->attach($partner->id);
        }

        // First, generate a draft workout
        $generateResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/workout-sessions/generate', []);

        $generateResponse->assertStatus(201);
        $sessionId = $generateResponse->json('data.id');

        // Verify it's in draft status
        $this->assertEquals('draft', $generateResponse->json('data.status'));

        // Now confirm the workout
        $confirmResponse = $this->actingAs($user, 'sanctum')
            ->postJson("/api/workout-sessions/{$sessionId}/confirm");

        $confirmResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'is_auto_generated',
                    'status',
                    'rationale',
                    'exercises',
                ],
                'message',
            ]);

        $this->assertEquals('active', $confirmResponse->json('data.status'));
        $this->assertTrue($confirmResponse->json('data.is_auto_generated'));

        // Verify session was updated in database
        $session = WorkoutSession::find($sessionId);
        $this->assertNotNull($session);
        $this->assertEquals(WorkoutSessionStatus::Active, $session->status);
        $this->assertNotNull($session->performed_at); // Active sessions have performed_at set
    }

    public function test_generate_requires_complete_profile(): void
    {
        $user = User::factory()->create();
        // No profile created

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/workout-sessions/generate', []);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Please complete your profile before generating workouts.',
            ]);
    }

    public function test_generate_requires_fitness_goal(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'fitness_goal' => null,
            'training_experience' => TrainingExperience::Beginner,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/workout-sessions/generate', []);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Please set your fitness goal in your profile.',
            ]);
    }

    public function test_generate_requires_training_experience(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/workout-sessions/generate', []);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Please set your training experience level in your profile.',
            ]);
    }

    public function test_generate_with_preferences(): void
    {
        // Ensure target region exists before creating user/exercises
        $targetRegion = TargetRegion::firstOrCreate(
            ['code' => 'UPPER_PUSH'],
            ['name' => 'Upper Push', 'display_order' => 10]
        );

        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile()->update([
            'fitness_goal' => FitnessGoal::Strength,
            'training_experience' => TrainingExperience::Advanced,
        ]);

        // Create properly classified exercises with target region
        $exercises = Exercise::factory()->count(5)->press()->barbell()->flat()->create();
        foreach ($exercises as $exercise) {
            $exercise->update(['target_region_id' => $targetRegion->id]);
            $exercise->partners()->attach($partner->id);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/workout-sessions/generate', [
                'target_regions' => ['UPPER_PUSH'],
                'duration_minutes' => 60,
                'difficulty' => 'advanced',
            ]);

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('data.exercises'));
        $this->assertEquals('draft', $response->json('data.status'));
    }

    public function test_generate_with_training_style_preference(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile()->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $trxType = EquipmentType::firstOrCreate(
            ['code' => 'TRX'],
            ['name' => 'TRX', 'display_order' => 90]
        );
        $functionalStyle = TrainingStyle::firstOrCreate(
            ['code' => 'FUNCTIONAL'],
            ['name' => 'Functional Training', 'display_order' => 20]
        );

        $bodybuildingStyle = TrainingStyle::firstOrCreate(
            ['code' => 'BODYBUILDING'],
            ['name' => 'Bodybuilding', 'display_order' => 10]
        );

        $trxExercise = Exercise::factory()->press()->flat()->create([
            'name' => 'TRX Chest Press',
            'equipment_type_id' => $trxType->id,
        ]);
        $barbellExercise = Exercise::factory()->press()->barbell()->flat()->create([
            'name' => 'Barbell Bench Press',
        ]);

        foreach ([$trxExercise, $barbellExercise] as $exercise) {
            $exercise->partners()->attach($partner->id);
        }
        $trxExercise->trainingStyles()->attach($functionalStyle->id);
        $barbellExercise->trainingStyles()->attach($bodybuildingStyle->id);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/workout-sessions/generate', [
                'target_regions' => ['UPPER_PUSH'],
                'training_styles' => ['FUNCTIONAL'],
                'duration_minutes' => 45,
            ]);

        $response->assertStatus(201);
        $selectedIds = collect($response->json('data.exercises'))
            ->pluck('session_exercise.exercise_id')
            ->all();
        $this->assertContains($trxExercise->id, $selectedIds);
        $this->assertNotContains($barbellExercise->id, $selectedIds);
    }

    public function test_generate_validation(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/workout-sessions/generate', [
                'duration_minutes' => 5, // Too low
                'difficulty' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['duration_minutes', 'difficulty']);
    }

    public function test_generate_handles_no_exercises_gracefully(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        // No exercises in database

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/workout-sessions/generate', []);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'No exercises available matching the specified criteria',
            ]);
    }

    public function test_confirm_requires_draft_status(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile()->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        // Create properly classified exercises
        $exercises = Exercise::factory()->count(3)->press()->barbell()->flat()->create();
        foreach ($exercises as $exercise) {
            $exercise->partners()->attach($partner->id);
        }

        // Generate a draft session
        $generateResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/workout-sessions/generate', []);

        $sessionId = $generateResponse->json('data.id');

        // Confirm it once
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/workout-sessions/{$sessionId}/confirm");

        // Try to confirm again - should fail
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/workout-sessions/{$sessionId}/confirm");

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Only draft sessions can be confirmed',
            ]);
    }

    public function test_user_can_regenerate_draft_workout(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile()->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        // Create many exercises to allow for shuffling variety
        $exercises = Exercise::factory()->count(20)->press()->barbell()->flat()->create();
        foreach ($exercises as $exercise) {
            $exercise->partners()->attach($partner->id);
        }

        // Generate first draft session
        $response1 = $this->actingAs($user, 'sanctum')
            ->postJson('/api/workout-sessions/generate', []);

        $response1->assertStatus(201);
        $session1Id = $response1->json('data.id');
        $this->assertEquals('draft', $response1->json('data.status'));

        // Regenerate - should cancel old session and create new one
        $response2 = $this->actingAs($user, 'sanctum')
            ->postJson("/api/workout-sessions/{$session1Id}/regenerate", []);

        $response2->assertStatus(201);
        $session2Id = $response2->json('data.id');
        $this->assertEquals('draft', $response2->json('data.status'));
        $this->assertEquals($session1Id, $response2->json('data.replaced_session_id'));

        // Verify first session is cancelled
        $session1 = WorkoutSession::find($session1Id);
        $this->assertEquals(WorkoutSessionStatus::Cancelled, $session1->status);

        // Verify second session exists and is draft
        $session2 = WorkoutSession::find($session2Id);
        $this->assertEquals(WorkoutSessionStatus::Draft, $session2->status);
        $this->assertEquals($session1Id, $session2->replaced_session_id);
    }

    public function test_regenerate_requires_draft_status(): void
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $user->profile()->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        // Create properly classified exercises
        $exercises = Exercise::factory()->count(3)->press()->barbell()->flat()->create();
        foreach ($exercises as $exercise) {
            $exercise->partners()->attach($partner->id);
        }

        // Generate and confirm a session
        $generateResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/workout-sessions/generate', []);

        $sessionId = $generateResponse->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/workout-sessions/{$sessionId}/confirm");

        // Try to regenerate an active session - should fail
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/workout-sessions/{$sessionId}/regenerate", []);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Only draft sessions can be regenerated',
            ]);
    }
}
