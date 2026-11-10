<?php

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\UnitSystem;
use App\Enums\WorkoutSessionStatus;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\Plan;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
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
        return WorkoutTemplate::factory()->create([
            'plan_id' => Plan::factory()->create(['user_id' => $user->id])->id,
            'name' => $isDeload ? 'Push Day (Deload)' : 'Push Day',
            'week_number' => $isDeload ? 8 : 1,
            'is_deload' => $isDeload,
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
            $progression->forExercise($exercise, $user)
        );
        $this->assertSame(31, $workingTargets['total_reps_target']);

        $deloadRow = $rowFor(isDeload: true);
        $deloadTargets = $progression->targetsFor(
            $deloadRow,
            $progression->forExercise($exercise, $user)
        );
        $this->assertNull($deloadTargets['total_reps_target']);
    }

    /**
     * A Deload Week holds the Working Weight (ADR-0011): a topped working week
     * earns a raise, but the deload session does not take it, and its banner
     * says nothing beyond "working". The raise arrives in the next working
     * session, judged against the last working session.
     */
    public function test_a_deload_session_after_a_topped_week_holds_the_weight(): void
    {
        $user = $this->member();
        $press = Exercise::factory()->press()->barbell()->flat()->create();
        $working = $this->templateWithRow($user, $press, isDeload: false, minReps: 8, maxReps: 12);
        $deload = $this->templateWithRow($user, $press, isDeload: true, minReps: 3, maxReps: 4);

        $this->performSession($user, $working, [[60, 12], [60, 12], [60, 12]]);

        $deloadSession = $this->start($user, $deload);
        $deloadRow = $deloadSession['exercises'][0]['session_exercise'];

        $this->assertSame('working', $deloadRow['progression_status']);
        $this->assertEquals(60, $deloadRow['target_weight'], 'no raise in a Deload Week');
        $this->assertNull($deloadRow['target_weight_lowered']);

        // The deload's 3–4 reps would top its own range; deload sessions are
        // not performance history (ADR-0008), so the earned raise still arrives.
        $this->logAndComplete($user, $deloadSession, [[60, 4], [60, 4]]);

        $nextRow = $this->start($user, $working)['exercises'][0]['session_exercise'];
        $this->assertSame('ready', $nextRow['progression_status']);
        $this->assertEquals(62.5, $nextRow['target_weight']);
    }

    public function test_a_deload_session_after_a_below_minimum_week_offers_no_lighter_weight(): void
    {
        $user = $this->member();
        $press = Exercise::factory()->press()->barbell()->flat()->create();
        $working = $this->templateWithRow($user, $press, isDeload: false, minReps: 8, maxReps: 12);
        $deload = $this->templateWithRow($user, $press, isDeload: true, minReps: 3, maxReps: 4);

        $this->performSession($user, $working, [[60, 5], [60, 5], [60, 5]]);

        $deloadRow = $this->start($user, $deload)['exercises'][0]['session_exercise'];

        $this->assertSame('working', $deloadRow['progression_status']);
        $this->assertEquals(60, $deloadRow['target_weight']);
        $this->assertNull($deloadRow['target_weight_lowered']);
    }

    public function test_a_deload_session_with_no_working_history_reads_as_new(): void
    {
        $user = $this->member();
        $press = Exercise::factory()->press()->barbell()->flat()->create();
        $deload = $this->templateWithRow($user, $press, isDeload: true, minReps: 3, maxReps: 4);

        $deloadRow = $this->start($user, $deload)['exercises'][0]['session_exercise'];

        $this->assertSame('no_history', $deloadRow['progression_status']);
        $this->assertNull($deloadRow['target_weight_lowered']);
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

    private function member(): User
    {
        $user = User::factory()->entitled()->create();
        $user->profile()->update([
            'fitness_goal' => FitnessGoal::GeneralFitness,
            'unit_system' => UnitSystem::Metric,
        ]);

        return $user->fresh('profile');
    }

    private function templateWithRow(User $user, Exercise $exercise, bool $isDeload, int $minReps, int $maxReps): WorkoutTemplate
    {
        $template = $this->templateFor($user, $isDeload);

        WorkoutTemplateExercise::create([
            'workout_template_id' => $template->id,
            'exercise_id' => $exercise->id,
            'order' => 0,
            'target_sets' => $isDeload ? 2 : 3,
            'min_target_reps' => $minReps,
            'max_target_reps' => $maxReps,
            'target_weight' => 0,
            'rest_seconds' => 90,
        ]);

        return $template;
    }

    /**
     * @return array<string, mixed> the started session's payload
     */
    private function start(User $user, WorkoutTemplate $template): array
    {
        return $this->actingAs($user, 'sanctum')
            ->postJson('/api/workout-sessions/start', ['template_id' => $template->id])
            ->assertCreated()
            ->json('data');
    }

    /**
     * @param  list<array{0: float|int, 1: int}>  $sets  [weight, reps] pairs
     */
    private function performSession(User $user, WorkoutTemplate $template, array $sets): void
    {
        $this->logAndComplete($user, $this->start($user, $template), $sets);
    }

    /**
     * @param  array<string, mixed>  $session  a started session's payload
     * @param  list<array{0: float|int, 1: int}>  $sets  [weight, reps] pairs, logged on its first row
     */
    private function logAndComplete(User $user, array $session, array $sets): void
    {
        $row = $session['exercises'][0]['session_exercise'];

        foreach ($sets as $index => [$weight, $reps]) {
            $this->actingAs($user, 'sanctum')
                ->postJson("/api/workout-sessions/{$session['id']}/sets", [
                    'workout_session_exercise_id' => $row['id'],
                    'exercise_id' => $row['exercise_id'],
                    'set_number' => $index + 1,
                    'weight' => $weight,
                    'reps' => $reps,
                    'rest_seconds' => 90,
                ])->assertCreated();
        }

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/workout-sessions/{$session['id']}/complete")
            ->assertOk();

        $this->travel(1)->days();
    }
}
