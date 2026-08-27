<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Issue 002: point set logs at the session-exercise row that owns them.
 *
 * Until now a set was tied to (workout_session_id, exercise_id), so an exercise
 * appearing twice in one session had no way to say which occurrence a set
 * belonged to. Nullable for this migration: the follow-up that enforces NOT NULL
 * ships separately, once the backfill has been verified against production.
 */
return new class extends Migration
{
    private const INDEX = 'set_logs_session_exercise_set_number_index';

    private const NAME = 'add_workout_session_exercise_id_to_workout_session_set_logs_table';

    private const CHUNK = 1000;

    /** Ids logged per warning, so one line cannot balloon to the table's size. */
    private const SAMPLE = 20;

    public function up(): void
    {
        // Guarded per object, not as one block: DDL is not transactional in
        // MySQL, so a failure partway through leaves the earlier statements
        // applied. A single hasColumn() guard would then skip the rest forever.
        if (! Schema::hasColumn('workout_session_set_logs', 'workout_session_exercise_id')) {
            Schema::table('workout_session_set_logs', function (Blueprint $table) {
                $table->foreignId('workout_session_exercise_id')
                    ->nullable()
                    ->after('workout_session_id')
                    ->constrained('workout_session_exercises')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasIndex('workout_session_set_logs', self::INDEX)) {
            Schema::table('workout_session_set_logs', function (Blueprint $table) {
                // Named explicitly: the generated name would exceed MySQL's
                // 64-character identifier limit.
                $table->index(['workout_session_exercise_id', 'set_number'], self::INDEX);
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('workout_session_set_logs', 'workout_session_exercise_id')) {
            return;
        }

        Schema::table('workout_session_set_logs', function (Blueprint $table) {
            // Foreign key first: MySQL backs the constraint with this composite
            // index and refuses to drop an index a constraint still needs.
            // SQLite (a documented local-dev option) cannot drop foreign keys.
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['workout_session_exercise_id']);
            }

            if (Schema::hasIndex('workout_session_set_logs', self::INDEX)) {
                $table->dropIndex(self::INDEX);
            }

            $table->dropColumn('workout_session_exercise_id');
        });
    }

    /**
     * Attach every existing set log to its session-exercise row.
     *
     * Idempotent: only rows still NULL are considered, so re-running is safe and
     * a partial run can be resumed.
     *
     * Walks the table in bounded chunks rather than materialising every
     * unattached row at once — on a production-sized set-logs table the latter
     * is an out-of-memory waiting to happen.
     */
    private function backfill(): void
    {
        $ambiguousCount = 0;
        $ambiguousSample = [];

        DB::table('workout_session_set_logs')
            ->whereNull('workout_session_exercise_id')
            ->select(['id', 'workout_session_id', 'exercise_id'])
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($setLogs) use (&$ambiguousCount, &$ambiguousSample) {
                $matches = $this->resolveRowsFor($setLogs);

                // Group by target row so each chunk costs a handful of UPDATEs
                // rather than one per set log.
                $byRow = [];

                foreach ($setLogs as $setLog) {
                    $key = $setLog->workout_session_id.':'.$setLog->exercise_id;
                    $match = $matches[$key] ?? null;

                    if ($match === null) {
                        continue;
                    }

                    if ($match->candidate_count > 1) {
                        $ambiguousCount++;

                        if (count($ambiguousSample) < self::SAMPLE) {
                            $ambiguousSample[] = $setLog->id;
                        }
                    }

                    $byRow[$match->first_row_id][] = $setLog->id;
                }

                foreach ($byRow as $rowId => $setLogIds) {
                    DB::table('workout_session_set_logs')
                        ->whereIn('id', $setLogIds)
                        ->update(['workout_session_exercise_id' => $rowId]);
                }
            });

        if ($ambiguousCount > 0) {
            // A session already containing the same exercise twice cannot say
            // which row a legacy set belonged to — that was never recorded.
            // Attaching to the earliest row keeps the set visible; leaving it
            // NULL would hide logged training history outright.
            Log::warning('Set logs matched more than one session-exercise row during backfill; attached to the earliest row.', [
                'migration' => self::NAME,
                'count' => $ambiguousCount,
                'sample_set_log_ids' => $ambiguousSample,
            ]);
        }

        $this->reportOrphans();
    }

    /**
     * Resolve the candidate rows for one chunk of set logs, keyed by
     * "session:exercise". One query per chunk.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $setLogs
     * @return array<string, object>
     */
    private function resolveRowsFor($setLogs): array
    {
        $pairs = collect($setLogs)
            ->map(fn ($setLog) => [$setLog->workout_session_id, $setLog->exercise_id])
            ->unique(fn ($pair) => $pair[0].':'.$pair[1]);

        $rows = DB::table('workout_session_exercises')
            ->whereIn('workout_session_id', $pairs->map(fn ($pair) => $pair[0])->unique()->all())
            ->whereIn('exercise_id', $pairs->map(fn ($pair) => $pair[1])->unique()->all())
            ->groupBy('workout_session_id', 'exercise_id')
            ->select([
                'workout_session_id',
                'exercise_id',
                DB::raw('min(id) as first_row_id'),
                DB::raw('count(id) as candidate_count'),
            ])
            ->get();

        $matches = [];

        foreach ($rows as $row) {
            $matches[$row->workout_session_id.':'.$row->exercise_id] = $row;
        }

        return $matches;
    }

    /**
     * Sets whose exercise is no longer in the session at all — the residue of
     * exercise swaps made before this column existed. They stay NULL: there is
     * no row to attach them to.
     *
     * Note they are not entirely inert: if that exercise is later re-added to
     * the session, the read path's legacy fallback will surface them under the
     * new row. Deciding whether to delete or adopt them belongs with the
     * follow-up migration that makes this column NOT NULL (issue 007).
     */
    private function reportOrphans(): void
    {
        $count = DB::table('workout_session_set_logs')
            ->whereNull('workout_session_exercise_id')
            ->count();

        if ($count === 0) {
            return;
        }

        $sample = DB::table('workout_session_set_logs')
            ->whereNull('workout_session_exercise_id')
            ->orderBy('id')
            ->limit(self::SAMPLE)
            ->pluck('id')
            ->all();

        Log::warning('Set logs could not be attached to any session-exercise row; left unattached.', [
            'migration' => self::NAME,
            'count' => $count,
            'sample_set_log_ids' => $sample,
        ]);
    }
};
