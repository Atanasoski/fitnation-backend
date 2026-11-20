<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\SplitFocus;
use App\Enums\TrainingExperience;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutTemplateExercise;
use Database\Seeders\WorkoutSplitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCatalogExercises;
use Tests\TestCase;

/**
 * The member's Equipment Selection lives on their profile (#154). Program
 * generation (onboarding complete, Program regenerate) starts from it when the
 * request names none; Smart Workout never does, so a member can ask for Full
 * gym for one session.
 *
 * Observed at the API seam: the EquipmentTypes of the generated exercises.
 * TRX is Opt-in (ADR-0014), so it appears only when the selection names it.
 */
class EquipmentSelectionProfileTest extends TestCase
{
    use CreatesCatalogExercises;
    use RefreshDatabase;

    public function test_a_member_stores_an_equipment_selection_and_reads_it_back(): void
    {
        $user = $this->member();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', ['equipment_types' => ['BARBELL', 'DUMBBELL']])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('user.profile.equipment_types', ['BARBELL', 'DUMBBELL']);
    }

    public function test_an_empty_selection_is_stored_as_null_full_gym(): void
    {
        $user = $this->member(['equipment_types' => ['BARBELL']]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', ['equipment_types' => []])
            ->assertOk();

        $this->assertNull($user->profile->fresh()->equipment_types);
        $this->assertNull($user->profile->fresh()->getRawOriginal('equipment_types'));
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertJsonPath('user.profile.equipment_types', null);
    }

    public function test_an_explicit_null_clears_the_selection(): void
    {
        $user = $this->member(['equipment_types' => ['BARBELL']]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', ['equipment_types' => null])
            ->assertOk();

        $this->assertNull($user->profile->fresh()->equipment_types);
    }

    public function test_a_profile_save_that_omits_the_selection_keeps_it(): void
    {
        $user = $this->member(['equipment_types' => ['BARBELL']]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', ['age' => 30])
            ->assertOk();

        $this->assertSame(['BARBELL'], $user->profile->fresh()->equipment_types);
    }

    public function test_an_unknown_equipment_code_is_rejected(): void
    {
        $user = $this->member();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', ['equipment_types' => ['BARBELL', 'NOT_A_THING']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('equipment_types.1');
    }

    public function test_regenerate_uses_the_stored_selection_when_the_request_omits_it(): void
    {
        $user = $this->member(['equipment_types' => ['TRX']]);

        $this->actingAs($user, 'sanctum')->postJson('/api/plans/regenerate')->assertCreated();

        $this->assertContains('TRX', $this->programEquipment($user));
    }

    public function test_onboarding_complete_uses_the_stored_selection_when_the_request_omits_it(): void
    {
        $user = $this->member(['equipment_types' => ['TRX']]);

        $this->actingAs($user, 'sanctum')->postJson('/api/onboarding/complete')->assertCreated();

        $this->assertContains('TRX', $this->programEquipment($user));
    }

    public function test_a_request_selection_overrides_the_stored_one(): void
    {
        $user = $this->member(['equipment_types' => ['TRX']]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/plans/regenerate', ['equipment_types' => ['BARBELL', 'DUMBBELL', 'CABLE', 'MACHINE']])
            ->assertCreated();

        $this->assertNotContains('TRX', $this->programEquipment($user));
    }

    public function test_a_member_with_no_stored_selection_is_generated_for_as_before(): void
    {
        $user = $this->member();

        $this->actingAs($user, 'sanctum')->postJson('/api/plans/regenerate')->assertCreated();

        $equipment = $this->programEquipment($user);
        $this->assertNotContains('TRX', $equipment);
        $this->assertContains('BARBELL', $equipment);
    }

    public function test_smart_workout_ignores_the_stored_selection(): void
    {
        $user = $this->member(['equipment_types' => ['TRX']]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/workout-sessions/generate', [
                'target_regions' => ['UPPER_PUSH'],
                'duration_minutes' => 45,
            ])
            ->assertCreated();

        $equipment = Exercise::query()
            ->with('equipmentType')
            ->whereIn('id', collect($response->json('data.exercises'))->pluck('session_exercise.exercise_id'))
            ->get()
            ->map(fn ($exercise) => $exercise->equipmentType?->code)
            ->filter()
            ->unique();

        $this->assertNotContains('TRX', $equipment->all());
        $this->assertContains('BARBELL', $equipment->all());
    }

    /**
     * A member ready to generate, in a gym that also has TRX Exercises for
     * every Slot role (a TRX twin of each barbell lift).
     *
     * @param  array<string, mixed>  $profile
     */
    private function member(array $profile = []): User
    {
        $this->seed(WorkoutSplitSeeder::class);
        $this->seedFullGymCatalog();
        $this->seedTrxTwins();

        $user = User::factory()->entitled()->create(['onboarding_completed_at' => null]);
        $user->profile->update(array_merge([
            'fitness_goal' => FitnessGoal::GeneralFitness,
            'training_experience' => TrainingExperience::Intermediate,
            'training_days_per_week' => 4,
            'workout_duration_minutes' => 60,
            'split_focus' => SplitFocus::Balanced,
        ], $profile));

        return $user->fresh();
    }

    private function seedTrxTwins(): void
    {
        EquipmentType::firstOrCreate(['code' => 'TRX'], ['name' => 'TRX', 'display_order' => 90]);

        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'TRX', 'FLAT');
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'TRX', 'INCLINE');
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'TRX', 'VERTICAL');
        $this->catalogExercise('ROW', 'UPPER_PULL', 'TRX', 'HORIZONTAL');
        $this->catalogExercise('SQUAT', 'LOWER', 'TRX');
        $this->catalogExercise('HINGE', 'LOWER', 'TRX');
    }

    /**
     * The EquipmentType codes across the member's active Program.
     *
     * @return list<string>
     */
    private function programEquipment(User $user): array
    {
        $templateIds = $user->fresh()->activeProgram->workoutTemplates->pluck('id');

        return WorkoutTemplateExercise::query()
            ->whereIn('workout_template_id', $templateIds)
            ->with('exercise.equipmentType')
            ->get()
            ->map(fn ($row) => $row->exercise->equipmentType?->code)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
