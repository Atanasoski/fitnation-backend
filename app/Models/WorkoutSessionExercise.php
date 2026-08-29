<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutSessionExercise extends Model
{
    protected $fillable = [
        'workout_session_id',
        'exercise_id',
        'order',
        'target_sets',
        'min_target_reps',
        'max_target_reps',
        'target_weight',
        'rest_seconds',
    ];

    protected $casts = [
        'target_weight' => 'decimal:2',
    ];

    /**
     * Relationship: WorkoutSessionExercise belongs to WorkoutSession
     */
    public function workoutSession(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class);
    }

    /**
     * Pick this row's sets out of an already-loaded session set-log collection.
     *
     * Matches on the row id, so an exercise occupying two rows in one session
     * does not show the same sets under both.
     *
     * Set logs written before workout_session_exercise_id existed carry null.
     * They are matched on the old (session, exercise) pair — without that they
     * would silently disappear from the response — but only when this row is
     * the exercise's sole occuprence in the session. With duplicates there is
     * no way to tell which row such a set belonged to, and showing it under
     * both is the double-counting this change exists to remove. That fallback
     * retires with the follow-up migration making the column NOT NULL.
     *
     * @param  \Illuminate\Support\Collection<int, SetLog>  $setLogs
     * @param  bool  $matchLegacySets  false when the exercise occupies several rows
     * @return \Illuminate\Support\Collection<int, SetLog>
     */
    public function ownedSetsFrom($setLogs, bool $matchLegacySets = true)
    {
        return $setLogs
            ->filter(fn ($setLog) => $setLog->workout_session_exercise_id === null
                ? $matchLegacySets && $setLog->exercise_id === $this->exercise_id
                : $setLog->workout_session_exercise_id === $this->id
            )
            ->sortBy('set_number')
            ->values();
    }

    /**
     * Whether this row is the only one in its session carrying its exercise.
     *
     * Costs a query; prefer passing the answer in when the caller already holds
     * the session's rows.
     */
    public function isOnlyRowForItsExercise(): bool
    {
        return static::where('workout_session_id', $this->workout_session_id)
            ->where('exercise_id', $this->exercise_id)
            ->count() <= 1;
    }

    /**
     * Relationship: WorkoutSessionExercise has many SetLogs
     */
    public function setLogs(): HasMany
    {
        return $this->hasMany(SetLog::class)->orderBy('set_number');
    }

    /**
     * Relationship: WorkoutSessionExercise belongs to Exercise
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
