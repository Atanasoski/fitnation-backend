<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutTemplateExercise extends Model
{
    protected $fillable = [
        'workout_template_id',
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
     * Relationship: WorkoutTemplateExercise belongs to WorkoutTemplate
     */
    public function workoutTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkoutTemplate::class);
    }

    /**
     * Relationship: WorkoutTemplateExercise belongs to Exercise
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
