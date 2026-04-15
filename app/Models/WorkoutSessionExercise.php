<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     * Relationship: WorkoutSessionExercise belongs to Exercise
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
