<?php

namespace Tests\Feature;

use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The migration that adds workout_session_exercise_id backfills existing rows.
 * Exercised here by inserting legacy-shaped set logs (column left null) and
 * re-running the backfill, which is what a production deploy does to rows
 * written before the column existed.
 */
class SetLogBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_attaches_sets_to_their_only_matching_row(): void
    {
        $session = $this->makeSession();
        $exercise = Exercise::factory()->create();
        $row = $this->row($session, $exercise, 0);

        $setLogId = $this->legacySetLog($session, $exercise, 1);

        $this->runBackfill();

        $this->assertSame($row->id, $this->attachedRowId($setLogId));
    }

    public function test_backfill_attaches_ambiguous_sets_to_the_earliest_row(): void
    {
        $session = $this->makeSession();
        $exercise = Exercise::factory()->create();
        $first = $this->row($session, $exercise, 0);
        $this->row($session, $exercise, 1);

        $setLogId = $this->legacySetLog($session, $exercise, 1);

        $this->runBackfill();

        $this->assertSame(
            $first->id,
            $this->attachedRowId($setLogId),
            'A legacy set matching two rows must stay visible under the earliest, not be dropped.'
        );
    }

    public function test_backfill_leaves_true_orphans_unattached(): void
    {
        $session = $this->makeSession();
        $inSession = Exercise::factory()->create();
        $swappedAway = Exercise::factory()->create();
        $this->row($session, $inSession, 0);

        // No row carries this exercise — the residue of an old swap.
        $setLogId = $this->legacySetLog($session, $swappedAway, 1);

        $this->runBackfill();

        $this->assertNull($this->attachedRowId($setLogId));
    }

    public function test_backfill_is_idempotent_and_does_not_repoint_attached_rows(): void
    {
        $session = $this->makeSession();
        $exercise = Exercise::factory()->create();
        $first = $this->row($session, $exercise, 0);
        $second = $this->row($session, $exercise, 1);

        // Already attached to the second row: a re-run must not drag it back to
        // the first just because both match on (session, exercise).
        $setLogId = $this->legacySetLog($session, $exercise, 1);
        DB::table('workout_session_set_logs')
            ->where('id', $setLogId)
            ->update(['workout_session_exercise_id' => $second->id]);

        $this->runBackfill();
        $this->runBackfill();

        $this->assertSame($second->id, $this->attachedRowId($setLogId));
        $this->assertNotSame($first->id, $this->attachedRowId($setLogId));
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Re-run only the backfill half of the migration, the schema already being
     * in place from RefreshDatabase.
     */
    private function runBackfill(): void
    {
        $migration = require database_path(
            'migrations/2026_08_27_191638_add_workout_session_exercise_id_to_workout_session_set_logs_table.php'
        );

        $backfill = (new \ReflectionClass($migration))->getMethod('backfill');
        $backfill->setAccessible(true);
        $backfill->invoke($migration);
    }

    private function makeSession(): WorkoutSession
    {
        return WorkoutSession::factory()->create([
            'user_id' => User::factory()->create()->id,
            'workout_template_id' => null,
            'performed_at' => now(),
            'completed_at' => null,
            'status' => WorkoutSessionStatus::Active,
        ]);
    }

    private function row(WorkoutSession $session, Exercise $exercise, int $order): WorkoutSessionExercise
    {
        return WorkoutSessionExercise::create([
            'workout_session_id' => $session->id,
            'exercise_id' => $exercise->id,
            'order' => $order,
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 60,
            'rest_seconds' => 90,
        ]);
    }

    /**
     * Inserted through the query builder so the new column stays null, as it
     * would be for a row written before the migration.
     */
    private function legacySetLog(WorkoutSession $session, Exercise $exercise, int $setNumber): int
    {
        return DB::table('workout_session_set_logs')->insertGetId([
            'workout_session_id' => $session->id,
            'workout_session_exercise_id' => null,
            'exercise_id' => $exercise->id,
            'set_number' => $setNumber,
            'weight' => 60,
            'reps' => 10,
            'rest_seconds' => 90,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function attachedRowId(int $setLogId): ?int
    {
        $value = DB::table('workout_session_set_logs')
            ->where('id', $setLogId)
            ->value('workout_session_exercise_id');

        return $value === null ? null : (int) $value;
    }
}
