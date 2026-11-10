<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Starting a workout from a program snapshots the template's rows into the
 * session: each session row keeps its template row's position, Prescription
 * and Slot key, so the session still knows which Slot every exercise fills.
 */
class WorkoutSessionStartTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_session_started_from_a_generated_template_keeps_every_rows_prescription_and_slot(): void
    {
        $user = User::factory()->entitled()->create();
        $template = $this->generatedTemplateFor($user);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/workout-sessions/start', [
            'template_id' => $template->id,
        ]);

        $response->assertStatus(201);

        $session = WorkoutSession::findOrFail($response->json('data.id'));
        $copied = $session->workoutSessionExercises()
            ->orderBy('order')
            ->get()
            ->map(fn ($row) => [
                'exercise_id' => $row->exercise_id,
                'order' => $row->order,
                'target_sets' => $row->target_sets,
                'min_target_reps' => $row->min_target_reps,
                'max_target_reps' => $row->max_target_reps,
                'target_weight' => $row->target_weight,
                'rest_seconds' => $row->rest_seconds,
                'slot_key' => $row->slot_key,
            ])
            ->all();

        $exerciseIds = $template->workoutTemplateExercises()->orderBy('order')->pluck('exercise_id');

        $this->assertSame([
            [
                'exercise_id' => $exerciseIds[0],
                'order' => 0,
                'target_sets' => 4,
                'min_target_reps' => 6,
                'max_target_reps' => 10,
                'target_weight' => '60.00',
                'rest_seconds' => 150,
                'slot_key' => 'main_press',
            ],
            [
                'exercise_id' => $exerciseIds[1],
                'order' => 1,
                'target_sets' => 3,
                'min_target_reps' => 10,
                'max_target_reps' => 15,
                'target_weight' => '12.50',
                'rest_seconds' => 60,
                'slot_key' => 'side_delts',
            ],
            [
                // A row the member added to the generated template by hand
                // fills no Slot, and neither does its session copy.
                'exercise_id' => $exerciseIds[2],
                'order' => 2,
                'target_sets' => 3,
                'min_target_reps' => 8,
                'max_target_reps' => 12,
                'target_weight' => '0.00',
                'rest_seconds' => 90,
                'slot_key' => null,
            ],
        ], $copied);
    }

    /**
     * A program workout as the generator writes it: every generated row
     * carries the Slot it fills. The last row was added by hand.
     */
    private function generatedTemplateFor(User $user): WorkoutTemplate
    {
        $template = WorkoutTemplate::factory()->create([
            'plan_id' => Plan::factory()->create(['user_id' => $user->id])->id,
            'name' => 'Push Day',
        ]);

        $rows = [
            ['order' => 0, 'target_sets' => 4, 'min_target_reps' => 6, 'max_target_reps' => 10, 'target_weight' => 60, 'rest_seconds' => 150, 'slot_key' => 'main_press'],
            ['order' => 1, 'target_sets' => 3, 'min_target_reps' => 10, 'max_target_reps' => 15, 'target_weight' => 12.5, 'rest_seconds' => 60, 'slot_key' => 'side_delts'],
            ['order' => 2, 'target_sets' => 3, 'min_target_reps' => 8, 'max_target_reps' => 12, 'target_weight' => 0, 'rest_seconds' => 90, 'slot_key' => null],
        ];

        foreach ($rows as $row) {
            WorkoutTemplateExercise::create([
                'workout_template_id' => $template->id,
                'exercise_id' => Exercise::factory()->create()->id,
                ...$row,
            ]);
        }

        return $template;
    }
}
