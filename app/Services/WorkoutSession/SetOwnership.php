<?php

namespace App\Services\WorkoutSession;

use App\Models\SetLog;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use Illuminate\Support\Collection;

/**
 * Which session-exercise row owns a given set log.
 *
 * A set log belongs to a row, not to an exercise: the same exercise may occupy
 * two rows in one session — a top set and a back-off block — and each row owns
 * its own sets.
 *
 * Sets written before workout_session_exercise_id existed carry null. They are
 * matched on the old (session, exercise) pair, so they stay visible rather than
 * silently vanishing — but only when their exercise occupies a single row. With
 * duplicates there is no way to tell which row such a set belonged to, and
 * showing it under both is double-counting. That fallback retires with the
 * migration making the column NOT NULL.
 *
 * That rule lives here and nowhere else, in both the forms callers need: an
 * in-memory partition of a loaded session (setsFor) and a query constraint
 * (constrain). It answers the duplicate-row question itself, once per session,
 * from the rows it holds — there is no flag for a caller to pass wrongly, which
 * is how the staff-facing view came to disagree with the API.
 */
final class SetOwnership
{
    /** Partition key for sets no row can claim. */
    private const UNATTRIBUTED = 'unattributed';

    /** @var Collection<int, WorkoutSessionExercise> rows in tiebreak order */
    private Collection $rows;

    /** @var Collection<array-key, Collection<int, SetLog>>|null */
    private ?Collection $partition = null;

    private function __construct(private WorkoutSession $session)
    {
        $this->rows = $session->workoutSessionExercises
            ->sortBy(fn (WorkoutSessionExercise $row) => [$row->order, $row->id])
            ->values();
    }

    /**
     * Loads only what it is missing, so a caller that has already refreshed the
     * session's relations keeps its copy rather than being handed a stale one.
     */
    public static function forSession(WorkoutSession $session): self
    {
        $session->loadMissing('workoutSessionExercises');

        return new self($session);
    }

    /**
     * The sets belonging to one row, ordered by set number.
     *
     * @return Collection<int, SetLog>
     */
    public function setsFor(WorkoutSessionExercise $row): Collection
    {
        return $this->partition()->get($row->id, collect());
    }

    /**
     * The session's sets that no row can claim: unattributable legacy sets, and
     * sets still pointing at a row that has since been removed.
     *
     * @return Collection<int, SetLog>
     */
    public function unattributedSets(): Collection
    {
        return $this->partition()->get(self::UNATTRIBUTED, collect());
    }

    /**
     * The row a set log is rendered under, or null when none can claim it.
     */
    public function rowFor(SetLog $setLog): ?WorkoutSessionExercise
    {
        if ($setLog->workout_session_exercise_id !== null) {
            return $this->rowById($setLog->workout_session_exercise_id);
        }

        $candidates = $this->rowsForExercise($setLog->exercise_id);

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    /**
     * One of the session's rows by id, or null if it is not one of them.
     */
    public function rowById(?int $rowId): ?WorkoutSessionExercise
    {
        return $rowId === null
            ? null
            : $this->rows->firstWhere('id', $rowId);
    }

    /**
     * Where a set arriving with an exercise but no row id belongs.
     *
     * Deployed clients that predate the column send only exercise_id. The
     * earliest row wins — the only defensible guess when the client cannot tell
     * us, and the same tiebreak the backfill migration used.
     */
    public function rowForExercise(?int $exerciseId): ?WorkoutSessionExercise
    {
        return $exerciseId === null
            ? null
            : $this->rowsForExercise($exerciseId)->first();
    }

    /**
     * Constrain a set-log query to one row's sets, session scope and legacy
     * fallback included, selecting exactly what setsFor() returns.
     *
     * Self-nesting: safe to apply directly to a query that carries other
     * conditions.
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function constrain($query, WorkoutSessionExercise $row)
    {
        $query->where('workout_session_id', $this->session->id);

        return $query->where(function ($scoped) use ($row) {
            $scoped->where('workout_session_exercise_id', $row->id);

            // Sweeping a legacy set that could belong to either of two rows
            // would take data the survivor still has a claim on, so only the
            // sole row for an exercise reaches for them.
            if ($this->rowsForExercise($row->exercise_id)->count() === 1) {
                $scoped->orWhere(fn ($legacy) => $legacy
                    ->whereNull('workout_session_exercise_id')
                    ->where('exercise_id', $row->exercise_id)
                );
            }
        });
    }

    /**
     * @return Collection<int, WorkoutSessionExercise>
     */
    private function rowsForExercise(int $exerciseId): Collection
    {
        return $this->rows->where('exercise_id', $exerciseId)->values();
    }

    /**
     * The session's sets grouped by owning row id, computed once and only if
     * asked for: the write paths need the predicate but not the sets.
     *
     * @return Collection<array-key, Collection<int, SetLog>>
     */
    private function partition(): Collection
    {
        return $this->partition ??= $this->session
            ->loadMissing('setLogs')
            ->setLogs
            ->sortBy('set_number')
            ->groupBy(fn (SetLog $setLog) => $this->rowFor($setLog)?->id ?? self::UNATTRIBUTED)
            ->map(fn (Collection $sets) => $sets->values());
    }
}
