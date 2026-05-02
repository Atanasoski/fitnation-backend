<?php

namespace Tests\Feature;

use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwapExerciseTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Workout Template – swap exercise
    // -------------------------------------------------------------------------

    public function test_user_can_swap_exercise_in_workout_template(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['user_id' => $user->id]);
        $template = WorkoutTemplate::factory()->create(['plan_id' => $plan->id]);

        $originalExercise = Exercise::factory()->create();
        $newExercise = Exercise::factory()->create();

        $pivot = WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $originalExercise->id,
            'order' => 0,
            'target_sets' => 4,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 50.00,
            'rest_seconds' => 90,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/workout-templates/{$template->id}/exercises/{$pivot->id}/swap", [
                'exercise_id' => $newExercise->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'exercises']]);

        $this->assertDatabaseHas('workout_template_exercises', [
            'id' => $pivot->id,
            'exercise_id' => $newExercise->id,
            'order' => 0,
            'target_sets' => 4,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'rest_seconds' => 90,
        ]);
    }

    public function test_swap_template_exercise_preserves_all_other_pivot_fields(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['user_id' => $user->id]);
        $template = WorkoutTemplate::factory()->create(['plan_id' => $plan->id]);

        $originalExercise = Exercise::factory()->create();
        $newExercise = Exercise::factory()->create();

        $pivot = WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $originalExercise->id,
            'order' => 2,
            'target_sets' => 5,
            'min_target_reps' => 3,
            'max_target_reps' => 5,
            'target_weight' => 100.00,
            'rest_seconds' => 180,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/workout-templates/{$template->id}/exercises/{$pivot->id}/swap", [
                'exercise_id' => $newExercise->id,
            ]);

        $pivot->refresh();
        $this->assertEquals($newExercise->id, $pivot->exercise_id);
        $this->assertEquals(2, $pivot->order);
        $this->assertEquals(5, $pivot->target_sets);
        $this->assertEquals(3, $pivot->min_target_reps);
        $this->assertEquals(5, $pivot->max_target_reps);
        $this->assertEquals('100.00', $pivot->target_weight);
        $this->assertEquals(180, $pivot->rest_seconds);
    }

    public function test_swap_template_exercise_returns_404_when_pivot_belongs_to_different_template(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['user_id' => $user->id]);
        $template = WorkoutTemplate::factory()->create(['plan_id' => $plan->id]);
        $otherTemplate = WorkoutTemplate::factory()->create(['plan_id' => $plan->id]);

        $exercise = Exercise::factory()->create();
        $newExercise = Exercise::factory()->create();

        $pivot = WorkoutTemplateExercise::create([
            'workout_template_id' => $otherTemplate->id,
            'exercise_id' => $exercise->id,
            'order' => 0,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/workout-templates/{$template->id}/exercises/{$pivot->id}/swap", [
                'exercise_id' => $newExercise->id,
            ]);

        $response->assertStatus(404);

        $this->assertDatabaseHas('workout_template_exercises', [
            'id' => $pivot->id,
            'exercise_id' => $exercise->id,
        ]);
    }

    public function test_swap_template_exercise_returns_403_when_user_does_not_own_template(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $plan = Plan::factory()->create(['user_id' => $owner->id]);
        $template = WorkoutTemplate::factory()->create(['plan_id' => $plan->id]);

        $exercise = Exercise::factory()->create();
        $newExercise = Exercise::factory()->create();

        $pivot = WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise->id,
            'order' => 0,
        ]);

        $response = $this->actingAs($other, 'sanctum')
            ->patchJson("/api/workout-templates/{$template->id}/exercises/{$pivot->id}/swap", [
                'exercise_id' => $newExercise->id,
            ]);

        $response->assertStatus(404);
    }

    public function test_swap_template_exercise_requires_valid_exercise_id(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['user_id' => $user->id]);
        $template = WorkoutTemplate::factory()->create(['plan_id' => $plan->id]);

        $exercise = Exercise::factory()->create();
        $pivot = WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise->id,
            'order' => 0,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/workout-templates/{$template->id}/exercises/{$pivot->id}/swap", [
                'exercise_id' => 99999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['exercise_id']);
    }

    public function test_swap_template_exercise_requires_exercise_id_field(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['user_id' => $user->id]);
        $template = WorkoutTemplate::factory()->create(['plan_id' => $plan->id]);

        $exercise = Exercise::factory()->create();
        $pivot = WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise->id,
            'order' => 0,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/workout-templates/{$template->id}/exercises/{$pivot->id}/swap", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['exercise_id']);
    }

    public function test_swap_template_exercise_requires_authentication(): void
    {
        $plan = Plan::factory()->create();
        $template = WorkoutTemplate::factory()->create(['plan_id' => $plan->id]);
        $exercise = Exercise::factory()->create();

        $pivot = WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise->id,
            'order' => 0,
        ]);

        $response = $this->patchJson("/api/workout-templates/{$template->id}/exercises/{$pivot->id}/swap", [
            'exercise_id' => $exercise->id,
        ]);

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Workout Session – swap exercise
    // -------------------------------------------------------------------------

    public function test_user_can_swap_exercise_in_workout_session(): void
    {
        $user = User::factory()->create();

        $session = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Active,
        ]);

        $originalExercise = Exercise::factory()->create();
        $newExercise = Exercise::factory()->create();

        $sessionExercise = WorkoutSessionExercise::create([
            'workout_session_id' => $session->id,
            'exercise_id' => $originalExercise->id,
            'order' => 0,
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 60.00,
            'rest_seconds' => 60,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/workout-sessions/{$session->id}/exercises/{$sessionExercise->id}/swap", [
                'exercise_id' => $newExercise->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'exercises']]);

        $this->assertDatabaseHas('workout_session_exercises', [
            'id' => $sessionExercise->id,
            'exercise_id' => $newExercise->id,
            'order' => 0,
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'rest_seconds' => 60,
        ]);
    }

    public function test_swap_session_exercise_preserves_all_other_fields(): void
    {
        $user = User::factory()->create();

        $session = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Active,
        ]);

        $originalExercise = Exercise::factory()->create();
        $newExercise = Exercise::factory()->create();

        $sessionExercise = WorkoutSessionExercise::create([
            'workout_session_id' => $session->id,
            'exercise_id' => $originalExercise->id,
            'order' => 3,
            'target_sets' => 4,
            'min_target_reps' => 6,
            'max_target_reps' => 10,
            'target_weight' => 80.00,
            'rest_seconds' => 120,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/workout-sessions/{$session->id}/exercises/{$sessionExercise->id}/swap", [
                'exercise_id' => $newExercise->id,
            ]);

        $sessionExercise->refresh();
        $this->assertEquals($newExercise->id, $sessionExercise->exercise_id);
        $this->assertEquals(3, $sessionExercise->order);
        $this->assertEquals(4, $sessionExercise->target_sets);
        $this->assertEquals(6, $sessionExercise->min_target_reps);
        $this->assertEquals(10, $sessionExercise->max_target_reps);
        $this->assertEquals('80.00', $sessionExercise->target_weight);
        $this->assertEquals(120, $sessionExercise->rest_seconds);
    }

    public function test_swap_session_exercise_returns_404_when_row_belongs_to_different_session(): void
    {
        $user = User::factory()->create();

        $session = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Active,
        ]);
        $otherSession = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Active,
        ]);

        $exercise = Exercise::factory()->create();
        $newExercise = Exercise::factory()->create();

        $sessionExercise = WorkoutSessionExercise::create([
            'workout_session_id' => $otherSession->id,
            'exercise_id' => $exercise->id,
            'order' => 0,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/workout-sessions/{$session->id}/exercises/{$sessionExercise->id}/swap", [
                'exercise_id' => $newExercise->id,
            ]);

        $response->assertStatus(404);

        $this->assertDatabaseHas('workout_session_exercises', [
            'id' => $sessionExercise->id,
            'exercise_id' => $exercise->id,
        ]);
    }

    public function test_swap_session_exercise_returns_403_when_user_does_not_own_session(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $session = WorkoutSession::factory()->create([
            'user_id' => $owner->id,
            'status' => WorkoutSessionStatus::Active,
        ]);

        $exercise = Exercise::factory()->create();
        $newExercise = Exercise::factory()->create();

        $sessionExercise = WorkoutSessionExercise::create([
            'workout_session_id' => $session->id,
            'exercise_id' => $exercise->id,
            'order' => 0,
        ]);

        $response = $this->actingAs($other, 'sanctum')
            ->patchJson("/api/workout-sessions/{$session->id}/exercises/{$sessionExercise->id}/swap", [
                'exercise_id' => $newExercise->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_swap_session_exercise_requires_valid_exercise_id(): void
    {
        $user = User::factory()->create();

        $session = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Active,
        ]);

        $exercise = Exercise::factory()->create();
        $sessionExercise = WorkoutSessionExercise::create([
            'workout_session_id' => $session->id,
            'exercise_id' => $exercise->id,
            'order' => 0,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/workout-sessions/{$session->id}/exercises/{$sessionExercise->id}/swap", [
                'exercise_id' => 99999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['exercise_id']);
    }

    public function test_swap_session_exercise_requires_exercise_id_field(): void
    {
        $user = User::factory()->create();

        $session = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'status' => WorkoutSessionStatus::Active,
        ]);

        $exercise = Exercise::factory()->create();
        $sessionExercise = WorkoutSessionExercise::create([
            'workout_session_id' => $session->id,
            'exercise_id' => $exercise->id,
            'order' => 0,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/workout-sessions/{$session->id}/exercises/{$sessionExercise->id}/swap", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['exercise_id']);
    }

    public function test_swap_session_exercise_requires_authentication(): void
    {
        $session = WorkoutSession::factory()->create(['status' => WorkoutSessionStatus::Active]);
        $exercise = Exercise::factory()->create();

        $sessionExercise = WorkoutSessionExercise::create([
            'workout_session_id' => $session->id,
            'exercise_id' => $exercise->id,
            'order' => 0,
        ]);

        $response = $this->patchJson("/api/workout-sessions/{$session->id}/exercises/{$sessionExercise->id}/swap", [
            'exercise_id' => $exercise->id,
        ]);

        $response->assertStatus(401);
    }
}
