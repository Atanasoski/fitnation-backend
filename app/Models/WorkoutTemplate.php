<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'name',
        'description',
        'day_of_week',
        'week_number',
        'order_index',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
    ];

    /**
     * Scope: order by day_of_week ascending, nulls last, then name.
     */
    public function scopeOrderedByDayOfWeek(Builder $query): Builder
    {
        return $query->orderByRaw('day_of_week IS NULL')->orderBy('day_of_week')->orderBy('name');
    }

    /**
     * Scope: order by program sequence (week_number, then order_index within week)
     */
    public function scopeOrderedByProgram(Builder $query): Builder
    {
        return $query->orderBy('week_number')->orderBy('order_index');
    }

    /**
     * Relationship: WorkoutTemplate belongs to Plan
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the user that owns this workout template through the plan
     */
    public function getUserAttribute()
    {
        return $this->plan?->user;
    }

    /**
     * Relationship: WorkoutTemplate has many WorkoutTemplateExercises
     */
    public function workoutTemplateExercises(): HasMany
    {
        return $this->hasMany(WorkoutTemplateExercise::class)->orderBy('order');
    }

    /**
     * Relationship: WorkoutTemplate belongs to many Exercises through WorkoutTemplateExercise
     */
    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class, 'workout_template_exercises')
            ->withPivot(['id', 'order', 'target_sets', 'min_target_reps', 'max_target_reps', 'target_weight', 'rest_seconds'])
            ->withTimestamps()
            ->orderBy('order');
    }

    /**
     * Relationship: WorkoutTemplate has many WorkoutSessions
     */
    public function workoutSessions(): HasMany
    {
        return $this->hasMany(WorkoutSession::class);
    }
}
