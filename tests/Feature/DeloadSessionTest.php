<?php

namespace Tests\Feature;

use App\Enums\WorkoutSessionStatus;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\Plan;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Models\WorkoutTemplate;
use App\Services\WorkoutSession\SessionProgression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deload sessions are not performance history (ADR-0008): the template flag is
 * copied onto the session at start, and both are visible to API clients.
 */
class DeloadSessionTest extends TestCase
{
    use RefreshDatabase;

    private function templateFor(User $user, bool $isDeload): WorkoutTemplate
    {
        $plan = Plan::factory()->create(['user_id' => $user->id]);

        return WorkoutTemplate::create([
            'plan_id' => $plan->id,
            'name' => $isDeload ? 'Push Day (Deload)' : 'Push Day',
            'day_of_week' => 0,
            'week_number' => $isDeload ? 8 : 1,
            'is_deload' => $isDeload,
            'order_index' => 0,
        ]);
    }

    public function test_starting_a_session_from_a_deload_template_marks_the_session(): void
    {
        $user = User::factory()->entitled()->create();
        $template = $this->templateFor($user, isDeload: true);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/workout-sessions/start', [
            'template_id' => $template->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.is_deload', true);

        $this->assertDatabaseHas('workout_sessions', [
            'user_id' => $user->id,
            'workout_template_id' => $template->id,
            'is_deload' => true,
        ]);
    }

    public function test_starting_a_session_from_a_working_template_does_not_mark_it(): void
    {
        $user = User::factory()->entitled()->create();
        $template = $this->templateFor($user, isDeload: false);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/workout-sessions/start', [
            'template_id' => $template->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.is_deload', false);
    }

    public function test_total_reps_target_is_suppressed_during_a_deload_session(): void
    {
        $user = User::factory()->entitled()->create();

        $bodyweight = EquipmentType::firstOrCreate(
            ['code' => 'BODYWEIGHT'],
            ['name' => 'Bodyweight', 'display_order' => 1]
        );
        $exercise = Exercise::factory()->create([
            'equipment_type_id' => $bodyweight->id,
            'angle_id' => null,
        ]);

        // A completed working session: 30 total reps of history.
        $working = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'performed_at' => now()->subWeek(),
            'completed_at' => now()->subWeek(),
            'status' => WorkoutSessionStatus::Completed,
        ]);
        foreach ([1, 2, 3] as $setNumber) {
            SetLog::create([
                'workout_session_id' => $working->id,
                'exercise_id' => $exercise->id,
                'set_number' => $setNumber,
                'weight' => 0,
                'reps' => 10,
                'rest_seconds' => 90,
            ]);
        }

        $rowFor = function (bool $isDeload) use ($user, $exercise): WorkoutSessionExercise {
            $session = WorkoutSession::factory()->create([
                'user_id' => $user->id,
                'performed_at' => now(),
                'status' => WorkoutSessionStatus::Active,
                'is_deload' => $isDeload,
            ]);

            return WorkoutSessionExercise::create([
                'workout_session_id' => $session->id,
                'exercise_id' => $exercise->id,
                'order' => 1,
                'target_sets' => 2,
            ]);
        };

        $progression = app(SessionProgression::class);

        $workingRow = $rowFor(isDeload: false);
        $workingTargets = $progression->targetsFor(
            $workingRow,
            $progression->forExercise($exercise, $user)['targets']
        );
        $this->assertSame(31, $workingTargets['total_reps_target']);

        $deloadRow = $rowFor(isDeload: true);
        $deloadTargets = $progression->targetsFor(
            $deloadRow,
            $progression->forExercise($exercise, $user)['targets']
        );
        $this->assertNull($deloadTargets['total_reps_target']);
    }

    public function test_previous_sets_come_from_the_last_working_session_not_the_deload(): void
    {
        $user = User::factory()->entitled()->create();
        $exercise = Exercise::factory()->create();

        $logSets = function (WorkoutSession $session, array $reps) use ($exercise): void {
            foreach ($reps as $index => $repCount) {
                SetLog::create([
                    'workout_session_id' => $session->id,
                    'exercise_id' => $exercise->id,
                    'set_number' => $index + 1,
                    'weight' => 80,
                    'reps' => $repCount,
                    'rest_seconds' => 90,
                ]);
            }
        };

        $working = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'performed_at' => now()->subWeek(),
            'completed_at' => now()->subWeek(),
            'status' => WorkoutSessionStatus::Completed,
        ]);
        $logSets($working, [10, 10, 10]);

        $deload = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'performed_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
            'status' => WorkoutSessionStatus::Completed,
            'is_deload' => true,
        ]);
        $logSets($deload, [3, 3]);

        $current = WorkoutSession::factory()->create([
            'user_id' => $user->id,
            'performed_at' => now(),
            'status' => WorkoutSessionStatus::Active,
        ]);

        $previous = $current->getPreviousSetLogsForExercises([$exercise->id]);

        $this->assertSame(
            [10, 10, 10],
            $previous[$exercise->id]->pluck('reps')->map(fn ($r) => (int) $r)->all()
        );
    }
}
