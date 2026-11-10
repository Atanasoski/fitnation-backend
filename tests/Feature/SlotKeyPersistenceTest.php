<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * slot_key is the trace from a generated row back to the Blueprint slot
 * that produced it (issue #107): nullable on both exercise-row tables, so
 * manual templates and user-added session exercises simply leave it null.
 */
class SlotKeyPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_exercise_rows_persist_their_slot_key(): void
    {
        $row = WorkoutTemplateExercise::create([
            'workout_template_id' => WorkoutTemplate::factory()->create()->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'order' => 1,
            'target_sets' => 3,
            'min_target_reps' => 6,
            'max_target_reps' => 10,
            'target_weight' => 0,
            'rest_seconds' => 90,
            'slot_key' => 'main_press',
        ]);

        $this->assertSame('main_press', $row->fresh()->slot_key);
    }

    public function test_session_exercise_rows_persist_their_slot_key(): void
    {
        $row = WorkoutSessionExercise::create([
            'workout_session_id' => WorkoutSession::factory()->create()->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'order' => 1,
            'target_sets' => 3,
            'min_target_reps' => 6,
            'max_target_reps' => 10,
            'target_weight' => 0,
            'rest_seconds' => 90,
            'slot_key' => 'heavy_row',
        ]);

        $this->assertSame('heavy_row', $row->fresh()->slot_key);
    }

    public function test_slot_key_is_nullable_for_rows_outside_generation(): void
    {
        $row = WorkoutTemplateExercise::create([
            'workout_template_id' => WorkoutTemplate::factory()->create()->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'order' => 1,
            'target_sets' => 3,
            'min_target_reps' => 6,
            'max_target_reps' => 10,
            'target_weight' => 0,
            'rest_seconds' => 90,
        ]);

        $this->assertNull($row->fresh()->slot_key);
    }
}
