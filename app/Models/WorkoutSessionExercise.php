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
        'slot_key',
    ];

    protected $casts = [
        'target_weight' => 'decimal:2',
    ];

    /**
     * Snapshot a template's rows into a session, in one insert.
     *
     * Each session row takes every column it shares with its template row:
     * the exercise, its order, its Prescription and its Slot key. "Shared"
     * means fillable on both models, so a column added to both is copied
     * without anyone remembering to list it here.
     *
     * @param  iterable<WorkoutTemplateExercise>  $templateRows
     */
    public static function copyFromTemplate(WorkoutSession $session, iterable $templateRows): void
    {
        $columns = array_values(array_intersect(
            (new WorkoutTemplateExercise)->getFillable(),
            (new self)->getFillable(),
        ));

        $now = now();
        $rows = collect($templateRows)
            ->map(fn (WorkoutTemplateExercise $templateRow) => [
                ...$templateRow->only($columns),
                'workout_session_id' => $session->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            self::insert($rows);
        }
    }

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
