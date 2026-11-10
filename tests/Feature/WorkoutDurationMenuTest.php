<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\TrainingExperience;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Session duration is a fixed menu: 30/45/60/90/120 minutes (issue #109).
 *
 * Generate, regenerate, and profile update accept exactly these values (the
 * admin preview's are in WorkoutPreviewTest). Tier resolution stays
 * threshold-based, so an off-menu stored duration still generates.
 */
class WorkoutDurationMenuTest extends TestCase
{
    use RefreshDatabase;

    private function entitledUserWithProfile(): User
    {
        $user = User::factory()->entitled()->create();
        $user->profile()->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        return $user;
    }

    private function userWithCatalog(): User
    {
        $partner = Partner::factory()->create();
        $user = User::factory()->entitled()->create(['partner_id' => $partner->id]);
        $user->profile()->update([
            'fitness_goal' => FitnessGoal::MuscleGain,
            'training_experience' => TrainingExperience::Intermediate,
        ]);

        $exercises = Exercise::factory()->count(5)->press()->barbell()->flat()->create();
        foreach ($exercises as $exercise) {
            $exercise->partners()->attach($partner->id);
        }

        return $user;
    }

    public function test_generate_rejects_durations_outside_the_menu(): void
    {
        $user = $this->entitledUserWithProfile();

        foreach ([15, 44, 75, 180] as $duration) {
            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/workout-sessions/generate', [
                    'duration_minutes' => $duration,
                ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['duration_minutes']);
        }
    }

    public function test_generate_accepts_every_menu_duration(): void
    {
        $user = $this->userWithCatalog();

        foreach ([30, 45, 60, 90, 120] as $duration) {
            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/workout-sessions/generate', [
                    'duration_minutes' => $duration,
                ]);

            $response->assertStatus(201);
        }
    }

    public function test_regenerate_rejects_durations_outside_the_menu(): void
    {
        $user = $this->userWithCatalog();

        $draftId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/workout-sessions/generate', [])
            ->assertStatus(201)
            ->json('data.id');

        foreach ([15, 44, 75, 180] as $duration) {
            $response = $this->actingAs($user, 'sanctum')
                ->postJson("/api/workout-sessions/{$draftId}/regenerate", [
                    'duration_minutes' => $duration,
                ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['duration_minutes']);
        }
    }

    public function test_regenerate_accepts_every_menu_duration(): void
    {
        $user = $this->userWithCatalog();

        $draftId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/workout-sessions/generate', [])
            ->assertStatus(201)
            ->json('data.id');

        foreach ([30, 45, 60, 90, 120] as $duration) {
            $response = $this->actingAs($user, 'sanctum')
                ->postJson("/api/workout-sessions/{$draftId}/regenerate", [
                    'duration_minutes' => $duration,
                ]);

            $response->assertStatus(201);

            // Regeneration replaces the draft; chain onto the new one.
            $draftId = $response->json('data.id');
        }
    }

    public function test_api_profile_update_rejects_durations_outside_the_menu(): void
    {
        $user = User::factory()->create();

        foreach ([1, 75, 600] as $duration) {
            $response = $this->actingAs($user, 'sanctum')
                ->putJson('/api/profile', [
                    'workout_duration_minutes' => $duration,
                ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['workout_duration_minutes']);
        }
    }

    public function test_api_profile_update_accepts_every_menu_duration(): void
    {
        $user = User::factory()->create();

        foreach ([30, 45, 60, 90, 120] as $duration) {
            $this->actingAs($user, 'sanctum')
                ->putJson('/api/profile', [
                    'workout_duration_minutes' => $duration,
                ])
                ->assertStatus(200);

            $this->assertSame($duration, $user->profile()->first()->workout_duration_minutes);
        }
    }

    public function test_web_profile_update_rejects_durations_outside_the_menu(): void
    {
        $user = User::factory()->create();

        foreach ([1, 75, 600] as $duration) {
            $response = $this->actingAs($user)
                ->patch('/profile', [
                    'name' => $user->name,
                    'email' => $user->email,
                    'workout_duration_minutes' => $duration,
                ]);

            $response->assertSessionHasErrors(['workout_duration_minutes']);
        }
    }

    public function test_web_profile_update_accepts_every_menu_duration(): void
    {
        $user = User::factory()->create();

        foreach ([30, 45, 60, 90, 120] as $duration) {
            $this->actingAs($user)
                ->patch('/profile', [
                    'name' => $user->name,
                    'email' => $user->email,
                    'workout_duration_minutes' => $duration,
                ])
                ->assertSessionHasNoErrors();

            $this->assertSame($duration, $user->profile()->first()->workout_duration_minutes);
        }
    }

    public function test_generation_still_works_for_an_off_menu_stored_duration(): void
    {
        $user = $this->userWithCatalog();

        // 75 was storable before the fixed menu. The migration moved every
        // such value onto it; tier resolution still reads one as a safety net.
        $user->profile()->update(['workout_duration_minutes' => 75]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/workout-sessions/generate', []);

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('data.exercises'));
    }
}
