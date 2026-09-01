<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutSessionExercise extends Model
{
    /**
     * The exercise relations a session-exercise row needs loaded to be
     * serialized without falling onto the lazy-load path.
     *
     * category/partners/muscleGroups feed ExerciseResource; movementPattern,
     * equipmentType and angle feed the progression calculation.
     *
     * Note the absence of targetRegion: nothing reads `target_region` off a
     * session response.
     */
    public const EXERCISE_RELATIONS = [
        'exercise.category',
        'exercise.partners',
        'exercise.muscleGroups',
        'exercise.primaryMuscleGroups',
        'exercise.secondaryMuscleGroups',
        'exercise.movementPattern',
        'exercise.equipmentType',
        'exercise.angle',
    ];

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
