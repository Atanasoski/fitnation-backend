<?php

namespace App\Models;

use App\Enums\PlanType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'partner_id',
        'name',
        'description',
        'cover_image',
        'is_active',
        'type',
        'duration_weeks',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'type' => PlanType::class,
        ];
    }

    /**
     * Relationship: Plan belongs to User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Plan belongs to Partner
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Relationship: Plan has many WorkoutTemplates
     */
    public function workoutTemplates(): HasMany
    {
        return $this->hasMany(WorkoutTemplate::class);
    }

    /**
     * Check if this plan is a program
     */
    public function isProgram(): bool
    {
        return $this->type === PlanType::Program;
    }

    /**
     * Check if this plan is a routine
     */
    public function isRoutine(): bool
    {
        return $this->type === PlanType::Routine;
    }

    /**
     * Check if this is a partner library plan
     */
    public function isPartnerLibraryPlan(): bool
    {
        return $this->partner_id !== null && $this->user_id === null;
    }

    /**
     * Check if this plan is partner-provided (library or browsable routine)
     */
    public function isPartnerProvided(): bool
    {
        return $this->partner_id !== null && $this->user_id === null;
    }

    /**
     * Scope: Filter plans for a specific partner (library plans)
     */
    public function scopeForPartner(Builder $query, int $partnerId): Builder
    {
        return $query->where('partner_id', $partnerId)->whereNull('user_id');
    }

    /**
     * Scope: Filter plans for a specific user
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get the next workout template for the user in this program
     */
    public function nextWorkout(User $user): ?WorkoutTemplate
    {
        if (! $this->isProgram()) {
            return null;
        }

        // Get all workout templates ordered by program sequence
        $templates = $this->workoutTemplates()
            ->orderedByProgram()
            ->with(['exercises.category', 'exercises.partners'])
            ->get();

        // Find the first template that hasn't been completed by this user
        foreach ($templates as $template) {
            $completedSession = $template->workoutSessions()
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->exists();

            if (! $completedSession) {
                return $template;
            }
        }

        // All workouts completed
        return null;
    }

    /**
     * Get the progress percentage for this program
     */
    public function getProgressPercentage(User $user): ?float
    {
        if (! $this->isProgram()) {
            return null;
        }

        $totalTemplates = $this->workoutTemplates()->count();

        if ($totalTemplates === 0) {
            return 0.0;
        }

        // Count completed templates
        $completedCount = 0;
        foreach ($this->workoutTemplates as $template) {
            $hasCompleted = $template->workoutSessions()
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->exists();

            if ($hasCompleted) {
                $completedCount++;
            }
        }

        return round(($completedCount / $totalTemplates) * 100, 2);
    }

    /**
     * Get the current active week for this program
     */
    public function getCurrentActiveWeek(User $user): ?int
    {
        if (! $this->isProgram()) {
            return null;
        }

        // Get the next incomplete workout
        $nextWorkout = $this->nextWorkout($user);

        if ($nextWorkout) {
            return $nextWorkout->week_number;
        }

        // All workouts completed - return the last week or duration_weeks
        $lastWeek = $this->workoutTemplates()
            ->max('week_number');

        if ($lastWeek) {
            return $lastWeek;
        }

        // No workouts exist - return duration_weeks if available, otherwise 1
        return $this->duration_weeks ?? 1;
    }
}
