<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Role;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks what deleting an exercise does today, through the admin web page and
 * the API, before the Archived Exercise rule changes it (spec 023, ticket 02).
 *
 * These describe current behaviour, not desired behaviour: a used exercise is
 * hard-deleted and its users' history goes with it through ON DELETE CASCADE.
 */
class ExerciseDestroyCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_page_deletes_an_unused_exercise(): void
    {
        $exercise = Exercise::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('exercises.destroy', $exercise))
            ->assertRedirect(route('exercises.index'))
            ->assertSessionHas('success', 'Exercise deleted successfully!');

        $this->assertDatabaseMissing('workout_exercises', ['id' => $exercise->id]);
    }

    public function test_the_admin_page_deletes_a_used_exercise_and_its_history_with_it(): void
    {
        ['exercise' => $exercise, 'rows' => $rows] = $this->usedExercise();

        $this->actingAs($this->admin())
            ->delete(route('exercises.destroy', $exercise))
            ->assertRedirect(route('exercises.index'));

        $this->assertDatabaseMissing('workout_exercises', ['id' => $exercise->id]);
        $this->assertDatabaseMissing('workout_template_exercises', ['id' => $rows['template']]);
        $this->assertDatabaseMissing('workout_session_exercises', ['id' => $rows['session']]);
        $this->assertDatabaseMissing('workout_session_set_logs', ['id' => $rows['set']]);
    }

    public function test_a_non_admin_cannot_delete_through_the_admin_page(): void
    {
        $exercise = Exercise::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('exercises.destroy', $exercise))
            ->assertForbidden();

        $this->assertDatabaseHas('workout_exercises', ['id' => $exercise->id]);
    }

    public function test_the_api_deletes_an_exercise(): void
    {
        $exercise = Exercise::factory()->create();

        $this->actingAs(User::factory()->entitled()->create(), 'sanctum')
            ->deleteJson("/api/exercises/{$exercise->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Exercise deleted successfully']);

        $this->assertDatabaseMissing('workout_exercises', ['id' => $exercise->id]);
    }

    public function test_the_api_deletes_a_used_exercise_and_its_history_with_it(): void
    {
        ['exercise' => $exercise, 'rows' => $rows] = $this->usedExercise();

        $this->actingAs(User::factory()->entitled()->create(), 'sanctum')
            ->deleteJson("/api/exercises/{$exercise->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Exercise deleted successfully']);

        $this->assertDatabaseMissing('workout_exercises', ['id' => $exercise->id]);
        $this->assertDatabaseMissing('workout_template_exercises', ['id' => $rows['template']]);
        $this->assertDatabaseMissing('workout_session_exercises', ['id' => $rows['session']]);
        $this->assertDatabaseMissing('workout_session_set_logs', ['id' => $rows['set']]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);

        return $admin;
    }

    /**
     * An exercise in a plan, a logged session exercise and a logged set.
     *
     * @return array{exercise: Exercise, rows: array{template: int, session: int, set: int}}
     */
    private function usedExercise(): array
    {
        $exercise = Exercise::factory()->create();
        $template = WorkoutTemplate::factory()->create();
        $session = WorkoutSession::factory()->create(['workout_template_id' => $template->id]);

        $templateRow = WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);
        $sessionRow = WorkoutSessionExercise::create([
            'workout_session_id' => $session->id,
            'exercise_id' => $exercise->id,
            'order' => 1,
        ]);
        $set = SetLog::create([
            'workout_session_id' => $session->id,
            'workout_session_exercise_id' => $sessionRow->id,
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'weight' => 60,
            'reps' => 8,
        ]);

        return ['exercise' => $exercise, 'rows' => [
            'template' => $templateRow->id,
            'session' => $sessionRow->id,
            'set' => $set->id,
        ]];
    }
}
