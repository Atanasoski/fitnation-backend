<?php

namespace App\Models;

use App\Enums\WorkoutSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkoutSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'workout_template_id',
        'performed_at',
        'completed_at',
        'notes',
        'is_auto_generated',
        'status',
        'replaced_session_id',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_auto_generated' => 'boolean',
        'status' => WorkoutSessionStatus::class,
    ];

    protected $appends = ['status_label', 'status_badge_classes'];

    /**
     * Display label for the session status (for UI badges).
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            WorkoutSessionStatus::Cancelled => 'Cancelled',
            WorkoutSessionStatus::Completed => 'Completed',
            WorkoutSessionStatus::Draft => 'Draft',
            WorkoutSessionStatus::Active => 'In Progress',
        };
    }

    /**
     * Tailwind classes for the status pill badge (rounded-full, index/card).
     */
    public function getStatusBadgeClassesAttribute(): string
    {
        return match ($this->status) {
            WorkoutSessionStatus::Cancelled => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
            WorkoutSessionStatus::Completed => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
            default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
        };
    }

    /**
     * Relationship: WorkoutSession belongs to User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: WorkoutSession belongs to WorkoutTemplate
     */
    public function workoutTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkoutTemplate::class);
    }

    /**
     * Relationship: WorkoutSession has many SetLogs
     */
    public function setLogs(): HasMany
    {
        return $this->hasMany(SetLog::class);
    }

    /**
     * Relationship: WorkoutSession has many WorkoutSessionExercises
     */
    public function workoutSessionExercises(): HasMany
    {
        return $this->hasMany(WorkoutSessionExercise::class)->orderBy('order');
    }

    /**
     * Get previous set logs for exercises in this session from the last completed session per exercise
     * Uses Eloquent but batches queries efficiently
     */
    public function getPreviousSetLogsForExercises(array $exerciseIds): \Illuminate\Support\Collection
    {
        if (empty($exerciseIds)) {
            return collect();
        }

        // Get all previous completed sessions for this user that have set logs for these exercises
        $previousSessions = WorkoutSession::query()
            ->where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->where('status', WorkoutSessionStatus::Completed)
            ->whereHas('setLogs', fn ($q) => $q->whereIn('exercise_id', $exerciseIds))
            ->with(['setLogs' => fn ($q) => $q->whereIn('exercise_id', $exerciseIds)->orderBy('set_number')])
            ->orderByDesc('completed_at')
            ->get();

        // Group by exercise_id and get the most recent session's sets for each exercise
        $result = collect();

        foreach ($exerciseIds as $exerciseId) {
            // Find the most recent session that has sets for this exercise
            $latestSession = $previousSessions
                ->filter(fn ($session) => $session->setLogs->contains('exercise_id', $exerciseId))
                ->first();

            if ($latestSession) {
                $sets = $latestSession->setLogs
                    ->where('exercise_id', $exerciseId)
                    ->sortBy('set_number')
                    ->values();

                if ($sets->isNotEmpty()) {
                    $result[$exerciseId] = $sets;
                }
            }
        }

        return $result;
    }

    /**
     * Relationship: WorkoutSession was replaced by another session (regeneration tracking)
     */
    public function replacedSession(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'replaced_session_id');
    }

    /**
     * Relationship: WorkoutSession replaced by another session
     */
    public function replacedBy(): HasOne
    {
        return $this->hasOne(WorkoutSession::class, 'replaced_session_id');
    }
}
