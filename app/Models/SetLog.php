<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SetLog extends Model
{
    protected $table = 'workout_session_set_logs';

    protected $fillable = [
        'workout_session_id',
        'workout_session_exercise_id',
        'exercise_id',
        'set_number',
        'weight',
        'reps',
        'rest_seconds',
    ];

    protected $casts = [
        'weight' => 'decimal:1',
        // Cast explicitly: the ownership checks compare this strictly, and a
        // driver returning ints as strings would fail both branches and hide
        // every set from the session response.
        'workout_session_exercise_id' => 'integer',
    ];

    /**
     * Relationship: SetLog belongs to WorkoutSession
     */
    public function workoutSession(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class);
    }

    /**
     * Relationship: SetLog belongs to the session-exercise row that owns it.
     *
     * This, not exercise_id, is what identifies which occurrence of an exercise
     * a set was logged against. exercise_id stays denormalized on the row so the
     * history and progression queries can filter on it directly.
     */
    public function workoutSessionExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutSessionExercise::class);
    }

    /**
     * Relationship: SetLog belongs to Exercise
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
